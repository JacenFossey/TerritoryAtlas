import importlib.util
import io
import json
import unittest
import urllib.parse
from pathlib import Path
from unittest.mock import patch

MODULE_PATH = Path(__file__).resolve().parents[1] / "import_boundaries.py"
SPEC = importlib.util.spec_from_file_location("import_boundaries", MODULE_PATH)
import_boundaries = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(import_boundaries)


class ImportBoundariesTest(unittest.TestCase):
    def test_slugify_normalizes_punctuation_and_accents(self):
        self.assertEqual("mattice-val-cote", import_boundaries.slugify("Mattice-Val Côté"))

    def test_merge_geometries_combines_polygon_and_multipolygon_parts(self):
        polygon = {
            "type": "Polygon",
            "coordinates": [[[-80.0, 44.0], [-79.0, 44.0], [-80.0, 44.0]]],
        }
        multipolygon = {
            "type": "MultiPolygon",
            "coordinates": [[[[-78.0, 43.0], [-77.0, 43.0], [-78.0, 43.0]]]],
        }

        geometry = import_boundaries.merge_geometries([polygon, multipolygon])

        self.assertEqual("MultiPolygon", geometry["type"])
        self.assertEqual(2, len(geometry["coordinates"]))

    def test_build_features_excludes_water_and_assigns_parent(self):
        source_features = [
            self.feature("Mainland"),
            self.feature("Islands", offset=0.2),
            self.feature("Water", offset=0.4),
        ]

        features = import_boundaries.build_features(
            source_features,
            "lower_tier",
            parent_ids={"REGIONAL MUNICIPALITY OF YORK": "on-munid-19000"},
        )

        self.assertEqual(1, len(features))
        feature = features[0]
        self.assertEqual("on-munid-19001", feature["id"])
        self.assertEqual("Vaughan", feature["properties"]["name"])
        self.assertEqual("on-munid-19000", feature["properties"]["parent_id"])
        self.assertEqual("MultiPolygon", feature["geometry"]["type"])
        self.assertEqual(2, len(feature["geometry"]["coordinates"]))

    def test_validate_collection_rejects_duplicate_ids(self):
        feature = import_boundaries.build_features([self.feature("Mainland")], "lower_tier")[0]
        collection = import_boundaries.feature_collection("Test", [feature, feature])

        with self.assertRaisesRegex(ValueError, "duplicate feature IDs"):
            import_boundaries.validate_collection(collection)

    def test_query_requests_wgs84_simplified_geojson(self):
        response = io.BytesIO(json.dumps({"type": "FeatureCollection", "features": []}).encode())

        with patch.object(import_boundaries.urllib.request, "urlopen", return_value=response) as urlopen:
            import_boundaries.query_geojson(
                "https://geo.example.test/MapServer/13",
                "MUNID = '19000'",
                0.0001,
            )

        request = urlopen.call_args.args[0]
        parameters = urllib.parse.parse_qs(request.data.decode())
        self.assertEqual(["4326"], parameters["outSR"])
        self.assertEqual(["geojson"], parameters["f"])
        self.assertEqual(["5"], parameters["geometryPrecision"])
        self.assertEqual(["0.0001"], parameters["maxAllowableOffset"])
        self.assertEqual(["MUNID = '19000'"], parameters["where"])

    def test_generated_major_collection_contains_the_exact_ggh_set(self):
        expected_ids = {
            "on-munid-14000", "on-munid-15000", "on-munid-15014", "on-munid-16002",
            "on-munid-18000", "on-munid-19000", "on-munid-20002", "on-munid-21000",
            "on-munid-22000", "on-munid-23000", "on-munid-23008", "on-munid-24000",
            "on-munid-25005", "on-munid-26000", "on-munid-28005", "on-munid-29002",
            "on-munid-29006", "on-munid-30000", "on-munid-43000", "on-munid-43042",
            "on-munid-43052",
        }
        path = Path(__file__).resolve().parents[3] / "public" / "geo" / "upper-single-tier.geojson"

        with path.open(encoding="utf-8") as file:
            collection = json.load(file)

        self.assertEqual(expected_ids, {feature["id"] for feature in collection["features"]})
        import_boundaries.validate_collection(collection, expected_ids)

    @staticmethod
    def feature(extent_type, offset=0.0):
        return {
            "type": "Feature",
            "properties": {
                "MUNID": "19001",
                "MUNICIPAL_NAME": "CITY OF VAUGHAN",
                "MUNICIPAL_NAME_SHORTFORM": "VAUGHAN",
                "MUNICIPAL_TYPE": "Lower Tier Municipality",
                "MUNICIPAL_AREA_EXTENT_TYPE": extent_type,
                "MAH_CODE": "27001",
                "ASSESSMENT_CODE": "1928",
                "UPPER_TIER_MUNICIPALITY": "REGIONAL MUNICIPALITY OF YORK",
            },
            "geometry": {
                "type": "Polygon",
                "coordinates": [[
                    [-79.6 + offset, 43.7],
                    [-79.5 + offset, 43.7],
                    [-79.6 + offset, 43.7],
                ]],
            },
        }


if __name__ == "__main__":
    unittest.main()
