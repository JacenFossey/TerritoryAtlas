#!/usr/bin/env python3
"""Build browser-ready GGH municipal boundary GeoJSON from Ontario GeoHub."""

from __future__ import annotations

import argparse
import json
import re
import tempfile
import unicodedata
import urllib.parse
import urllib.request
from collections import defaultdict
from pathlib import Path
from typing import Any

CATALOGUE_URL = "https://data.ontario.ca/en/dataset/municipal-boundaries"
LICENCE_NAME = "Open Government Licence – Ontario"
LICENCE_URL = "https://www.ontario.ca/page/open-government-licence-ontario"
UPPER_SERVICE_URL = (
    "https://ws.lioservices.lrc.gov.on.ca/arcgis2/rest/services/"
    "LIO_OPEN_DATA/LIO_Open03/MapServer/13"
)
LOWER_SERVICE_URL = (
    "https://ws.lioservices.lrc.gov.on.ca/arcgis2/rest/services/"
    "LIO_OPEN_DATA/LIO_Open03/MapServer/14"
)

UPPER_TIER_AREAS = {
    "14000": "Northumberland County",
    "15000": "Peterborough County",
    "18000": "Durham Region",
    "19000": "York Region",
    "21000": "Peel Region",
    "22000": "Dufferin County",
    "23000": "Wellington County",
    "24000": "Halton Region",
    "26000": "Niagara Region",
    "30000": "Waterloo Region",
    "43000": "Simcoe County",
}

SINGLE_TIER_AREAS = {
    "15014": "Peterborough",
    "16002": "Kawartha Lakes",
    "20002": "Toronto",
    "23008": "Guelph",
    "25005": "Hamilton",
    "28005": "Haldimand County",
    "29002": "Brant County",
    "29006": "Brantford",
    "43042": "Barrie",
    "43052": "Orillia",
}

SOURCE_FIELDS = [
    "MUNID",
    "MUNICIPAL_NAME",
    "MUNICIPAL_NAME_SHORTFORM",
    "MUNICIPAL_TYPE",
    "MUNICIPAL_AREA_EXTENT_TYPE",
    "MAH_CODE",
    "ASSESSMENT_CODE",
]


def slugify(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    return re.sub(r"[^a-z0-9]+", "-", normalized.lower()).strip("-")


def display_name(value: str) -> str:
    """Convert the source's uppercase short form to readable display text."""
    name = value.lower().title()
    return re.sub(r"\bMc([a-z])", lambda match: f"Mc{match.group(1).upper()}", name)


def sql_list(values: list[str]) -> str:
    return ",".join(f"'{value.replace(chr(39), chr(39) * 2)}'" for value in values)


def query_geojson(
    service_url: str,
    where: str,
    simplification: float,
    extra_fields: list[str] | None = None,
) -> dict[str, Any]:
    parameters = urllib.parse.urlencode(
        {
            "where": where,
            "outFields": ",".join(SOURCE_FIELDS + (extra_fields or [])),
            "returnGeometry": "true",
            "outSR": "4326",
            "geometryPrecision": "5",
            "maxAllowableOffset": str(simplification),
            "orderByFields": "MUNID,OBJECTID",
            "f": "geojson",
        }
    ).encode()
    request = urllib.request.Request(f"{service_url}/query", data=parameters)

    try:
        with urllib.request.urlopen(request, timeout=120) as response:
            payload = json.load(response)
    except (OSError, json.JSONDecodeError) as error:
        raise RuntimeError(f"Could not download Ontario boundary data: {error}") from error

    if "error" in payload:
        message = payload["error"].get("message", "Unknown ArcGIS error")
        raise RuntimeError(f"Ontario boundary service returned an error: {message}")

    if payload.get("type") != "FeatureCollection":
        raise RuntimeError("Ontario boundary service did not return a GeoJSON FeatureCollection")

    return payload


def merge_geometries(geometries: list[dict[str, Any]]) -> dict[str, Any]:
    polygons: list[list[Any]] = []

    for geometry in geometries:
        geometry_type = geometry.get("type")
        coordinates = geometry.get("coordinates")

        if geometry_type == "Polygon":
            polygons.append(coordinates)
        elif geometry_type == "MultiPolygon":
            polygons.extend(coordinates)
        else:
            raise ValueError(f"Expected polygon geometry, received {geometry_type!r}")

    if len(polygons) == 1:
        return {"type": "Polygon", "coordinates": polygons[0]}

    return {"type": "MultiPolygon", "coordinates": polygons}


def build_features(
    source_features: list[dict[str, Any]],
    area_type: str,
    names: dict[str, str] | None = None,
    parent_ids: dict[str, str] | None = None,
) -> list[dict[str, Any]]:
    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)

    for feature in source_features:
        properties = feature.get("properties", {})
        source_identifier = str(properties.get("MUNID", ""))
        extent_type = properties.get("MUNICIPAL_AREA_EXTENT_TYPE")

        if not source_identifier or not feature.get("geometry"):
            raise ValueError("Source feature is missing MUNID or geometry")

        if extent_type == "Water":
            continue

        grouped[source_identifier].append(feature)

    features = []

    for source_identifier, parts in grouped.items():
        properties = parts[0]["properties"]
        short_name = properties["MUNICIPAL_NAME_SHORTFORM"]
        name = names.get(source_identifier, display_name(short_name)) if names else display_name(short_name)
        identifier = f"on-munid-{source_identifier}"
        parent_id = parent_ids.get(properties.get("UPPER_TIER_MUNICIPALITY")) if parent_ids else None

        normalized_properties = {
            "id": identifier,
            "name": name,
            "slug": slugify(name),
            "area_type": area_type,
            "administrative_status": properties["MUNICIPAL_TYPE"],
            "source_identifier": source_identifier,
            "source_name": properties["MUNICIPAL_NAME"],
            "mah_code": properties.get("MAH_CODE"),
            "assessment_code": properties.get("ASSESSMENT_CODE"),
            "boundary_precision": "official",
            "is_ggh": True,
        }

        if parent_id:
            normalized_properties["parent_id"] = parent_id

        features.append(
            {
                "type": "Feature",
                "id": identifier,
                "properties": normalized_properties,
                "geometry": merge_geometries([part["geometry"] for part in parts]),
            }
        )

    return sorted(features, key=lambda feature: feature["id"])


def feature_collection(name: str, features: list[dict[str, Any]]) -> dict[str, Any]:
    return {
        "type": "FeatureCollection",
        "name": name,
        "source": {
            "name": "Ontario Municipal Boundaries",
            "catalogue_url": CATALOGUE_URL,
        },
        "licence": {
            "name": LICENCE_NAME,
            "url": LICENCE_URL,
        },
        "features": features,
    }


def validate_collection(collection: dict[str, Any], expected_ids: set[str] | None = None) -> None:
    if collection.get("type") != "FeatureCollection":
        raise ValueError("GeoJSON root must be a FeatureCollection")

    features = collection.get("features")
    if not isinstance(features, list) or not features:
        raise ValueError("GeoJSON FeatureCollection must contain features")

    identifiers = [feature.get("id") for feature in features]
    if len(identifiers) != len(set(identifiers)):
        raise ValueError("GeoJSON contains duplicate feature IDs")

    if identifiers != sorted(identifiers):
        raise ValueError("GeoJSON features are not deterministically ordered")

    if expected_ids is not None and set(identifiers) != expected_ids:
        missing = sorted(expected_ids - set(identifiers))
        unexpected = sorted(set(identifiers) - expected_ids)
        raise ValueError(f"GeoJSON feature IDs differ; missing={missing}, unexpected={unexpected}")

    for feature in features:
        geometry_type = feature.get("geometry", {}).get("type")
        if geometry_type not in {"Polygon", "MultiPolygon"}:
            raise ValueError(f"Feature {feature.get('id')} has invalid geometry {geometry_type!r}")
        properties = feature.get("properties", {})
        required = {"id", "name", "slug", "area_type", "source_identifier", "source_name"}
        if not required.issubset(properties):
            raise ValueError(f"Feature {feature.get('id')} is missing required properties")


def write_collection(path: Path, collection: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    content = json.dumps(collection, ensure_ascii=False, separators=(",", ":"), sort_keys=True) + "\n"

    with tempfile.NamedTemporaryFile("w", encoding="utf-8", dir=path.parent, delete=False) as file:
        file.write(content)
        temporary_path = Path(file.name)

    temporary_path.replace(path)


def load_and_validate(path: Path, expected_ids: set[str] | None = None) -> None:
    with path.open(encoding="utf-8") as file:
        validate_collection(json.load(file), expected_ids)


def import_boundaries(output_directory: Path, simplification: float) -> tuple[Path, Path]:
    land_filter = "MUNICIPAL_AREA_EXTENT_TYPE <> 'Water'"
    upper_where = f"MUNID IN ({sql_list(sorted(UPPER_TIER_AREAS))}) AND {land_filter}"
    single_where = f"MUNID IN ({sql_list(sorted(SINGLE_TIER_AREAS))}) AND {land_filter}"
    parent_names = sorted(
        {
            "COUNTY OF DUFFERIN",
            "COUNTY OF NORTHUMBERLAND",
            "COUNTY OF PETERBOROUGH",
            "COUNTY OF SIMCOE",
            "COUNTY OF WELLINGTON",
            "REGIONAL MUNICIPALITY OF DURHAM",
            "REGIONAL MUNICIPALITY OF HALTON",
            "REGIONAL MUNICIPALITY OF NIAGARA",
            "REGIONAL MUNICIPALITY OF PEEL",
            "REGIONAL MUNICIPALITY OF WATERLOO",
            "REGIONAL MUNICIPALITY OF YORK",
        }
    )
    lower_where = f"UPPER_TIER_MUNICIPALITY IN ({sql_list(parent_names)}) AND {land_filter}"

    upper_source = query_geojson(UPPER_SERVICE_URL, upper_where, simplification)
    single_source = query_geojson(LOWER_SERVICE_URL, single_where, simplification)
    lower_source = query_geojson(
        LOWER_SERVICE_URL,
        lower_where,
        simplification,
        ["UPPER_TIER_MUNICIPALITY"],
    )

    major_features = build_features(upper_source["features"], "upper_tier", UPPER_TIER_AREAS)
    major_features.extend(build_features(single_source["features"], "single_tier", SINGLE_TIER_AREAS))
    major_features.sort(key=lambda feature: feature["id"])

    upper_name_to_id = {
        feature["properties"]["source_name"]: feature["id"]
        for feature in major_features
        if feature["properties"]["area_type"] == "upper_tier"
    }
    lower_features = build_features(
        lower_source["features"],
        "lower_tier",
        parent_ids=upper_name_to_id,
    )

    major_collection = feature_collection("GGH upper and single-tier municipalities", major_features)
    lower_collection = feature_collection("GGH lower-tier municipalities", lower_features)
    expected_major_ids = {f"on-munid-{identifier}" for identifier in UPPER_TIER_AREAS | SINGLE_TIER_AREAS}

    validate_collection(major_collection, expected_major_ids)
    validate_collection(lower_collection)

    missing_parents = [
        feature["id"]
        for feature in lower_features
        if "parent_id" not in feature["properties"]
    ]
    if missing_parents:
        raise ValueError(f"Lower-tier features are missing parent IDs: {missing_parents}")

    upper_path = output_directory / "upper-single-tier.geojson"
    lower_path = output_directory / "lower-tier.geojson"
    write_collection(upper_path, major_collection)
    write_collection(lower_path, lower_collection)

    return upper_path, lower_path


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=Path(__file__).resolve().parents[2] / "public" / "geo",
        help="Directory for generated GeoJSON files (default: public/geo)",
    )
    parser.add_argument(
        "--simplification",
        type=float,
        default=0.0001,
        help="ArcGIS generalization tolerance in output degrees (default: 0.0001)",
    )
    parser.add_argument(
        "--validate-only",
        action="store_true",
        help="Validate the existing generated files without downloading data",
    )
    arguments = parser.parse_args()

    upper_path = arguments.output_dir / "upper-single-tier.geojson"
    lower_path = arguments.output_dir / "lower-tier.geojson"
    expected_major_ids = {f"on-munid-{identifier}" for identifier in UPPER_TIER_AREAS | SINGLE_TIER_AREAS}

    if arguments.validate_only:
        load_and_validate(upper_path, expected_major_ids)
        load_and_validate(lower_path)
        print(f"Validated {upper_path} and {lower_path}")
        return

    paths = import_boundaries(arguments.output_dir, arguments.simplification)
    for path in paths:
        size = path.stat().st_size / 1024
        with path.open(encoding="utf-8") as file:
            count = len(json.load(file)["features"])
        print(f"Wrote {path} ({count} features, {size:.1f} KiB)")


if __name__ == "__main__":
    main()
