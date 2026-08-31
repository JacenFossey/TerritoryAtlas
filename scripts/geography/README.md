# Geography import

This directory contains the reproducible import for TerritoryAtlas boundary assets. The importer uses only the Python 3 standard library; GDAL, GeoPandas, and other system packages are not required.

## Source data

The boundary geometry comes from the Province of Ontario's [Municipal boundaries catalogue entry](https://data.ontario.ca/en/dataset/municipal-boundaries):

- [Municipal Boundary — Upper Tier and District](https://geohub.lio.gov.on.ca/datasets/municipal-boundary-upper-tier-and-district/explore)
- [Municipal Boundary — Lower and Single Tier](https://geohub.lio.gov.on.ca/datasets/municipal-boundary-lower-and-single-tier)

The importer queries the public ArcGIS REST layers linked by those catalogue resources:

- Upper tier: `LIO_Open03/MapServer/13`
- Lower and single tier: `LIO_Open03/MapServer/14`

The explicit set of 21 GGH upper- and single-tier municipalities follows Ontario's [Growth Plan for the Greater Golden Horseshoe schedules](https://www.ontario.ca/document/growth-plan-greater-golden-horseshoe/schedules). Keeping that set in `import_boundaries.py` makes membership reviewable rather than inferring it from a bounding box.

Source data is licensed under the [Open Government Licence – Ontario](https://www.ontario.ca/page/open-government-licence-ontario). The generated files retain source identifiers and licence metadata. Changes may be made to the source datasets without notice; regenerate and review the output when updating boundaries.

## Generate the assets

From the repository root, run:

```bash
python3 scripts/geography/import_boundaries.py
```

This writes deterministic, compact GeoJSON to:

```text
public/geo/upper-single-tier.geojson
public/geo/lower-tier.geojson
```

The command:

1. requests WGS84 GeoJSON directly from Ontario's ArcGIS REST service;
2. selects the explicit 21 GGH major municipalities and lower tiers belonging to the GGH upper tiers;
3. excludes `Water` extent records so fills follow useful land/island geography and do not cover large portions of the basemap's lakes;
4. combines a municipality's mainland and island records into one Polygon or MultiPolygon;
5. applies a 0.0001-degree server-side generalization tolerance and five-decimal coordinate precision;
6. normalizes stable IDs and properties; and
7. validates and atomically replaces the generated files.

A feature's stable application-facing ID is based on Ontario's municipal ID, for example `on-munid-19000` for York Region. The original official name and municipal ID remain in `source_name` and `source_identifier`.

The outputs intentionally omit a generated timestamp so identical source responses produce byte-identical files. To compare a regeneration:

```bash
cp public/geo/upper-single-tier.geojson /tmp/upper-before.geojson
cp public/geo/lower-tier.geojson /tmp/lower-before.geojson
python3 scripts/geography/import_boundaries.py
cmp /tmp/upper-before.geojson public/geo/upper-single-tier.geojson
cmp /tmp/lower-before.geojson public/geo/lower-tier.geojson
```

## Validate and test

Validate committed assets without network access:

```bash
python3 scripts/geography/import_boundaries.py --validate-only
```

Run importer unit tests:

```bash
python3 -m unittest discover -s scripts/geography/tests -v
```

The importer fails rather than writing partial output when the source request, expected GGH major-area set, geometry type, stable ID uniqueness, lower-tier parent mapping, or required properties are invalid.
