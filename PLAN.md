# TerritoryAtlas — Product & Implementation Plan

> **Status:** PRs 0–10 complete; next up is PR 11 (V1 polish and release)
> **Primary goal:** Build an installable, map-first application that makes the geography of the Greater Golden Horseshoe intuitive: search a place someone mentions, immediately understand where it is, what larger areas contain it, and what is nearby.

---

## 1. Product vision

TerritoryAtlas starts as a geography-learning and territory-orientation tool for the Greater Golden Horseshoe (GGH), but its architecture should allow it to grow into a practical territory intelligence application.

The core question the product should answer is:

> **“Where is this place, what larger area is it part of, and what is nearby?”**

Examples:

- Search **Concord** → see Concord in Vaughan, inside York Region, inside the GTA and GGH; see Highway 400/407 and nearby Woodbridge, Maple, Richmond Hill and North York.
- Search **Cambridge** → see Cambridge inside Waterloo Region, in the GGH outer ring; see Highway 401 and nearby Kitchener, Waterloo, Guelph and Brantford.
- Search **Stoney Creek** → see it as a commonly used local area within Hamilton, rather than treating every place name as if it were an independent municipality.

Longer term, the same map can support operational layers such as customers, prospects, industrial areas, account revenue, opportunities, territories and route planning. The first release should **not** require any sales or customer data.

---

## 2. Product principles

### 2.1 Map first

The map is the primary interface, not a dashboard with a map card in it.

The default screen should be dominated by the map, with search, layers and a contextual detail panel around it.

### 2.2 Geography should progressively reveal itself

Do not display every possible boundary and label at every zoom level.

At a high level, show major GGH divisions. As the user zooms in, reveal municipalities, common place names and local context.

Suggested hierarchy:

1. Greater Golden Horseshoe
2. GTA / non-GTA context
3. Upper-tier and single-tier municipalities
4. Lower-tier municipalities
5. Commonly referenced local areas / communities
6. Roads, highways and landmarks from the basemap
7. Optional future operational layers

### 2.3 Official geography and common geography are different things

The app must clearly distinguish between:

- **Official administrative areas** with authoritative boundaries; and
- **Common place names** such as Concord, Woodbridge, Meadowvale, Rexdale, Dixie or Streetsville, where boundaries may be historical, locally understood, approximate or defined differently by different sources.

Never imply that an approximate neighbourhood/community boundary is a legal municipal boundary.

### 2.4 Source geographic facts, do not hand-draw them

Official boundaries must originate from authoritative/open geographic data wherever possible.

The initial source of truth for Ontario municipal boundaries should be the Province of Ontario municipal boundary datasets:

- Municipal Boundary — Upper Tier and District
- Municipal Boundary — Lower and Single Tier

Ontario catalogue:
https://data.ontario.ca/en/dataset/municipal-boundaries

The dataset is available under the **Open Government Licence — Ontario** and is available through download and ESRI REST services.

For the GGH extent/reference, use the Province of Ontario’s published Greater Golden Horseshoe planning material rather than inventing an application-specific GGH shape.

Reference:
https://www.ontario.ca/document/growth-plan-greater-golden-horseshoe/schedules

### 2.5 Keep the architecture boring until complexity earns its way in

Start with:

- one Laravel application;
- one SQLite database;
- one JavaScript map module;
- static/preprocessed GeoJSON where practical;
- Blade for the application shell;
- minimal Alpine/vanilla JavaScript outside the map.

Do **not** begin with microservices, a separate SPA repository, PostGIS, a tile server, queues, Redis, Elasticsearch or a separate API unless a real requirement appears.

### 2.6 Learn Laravel by building the real application

The project should expose Laravel concepts naturally rather than introducing infrastructure only for educational purposes.

Expected concepts include:

- routes;
- controllers;
- Blade;
- migrations;
- Eloquent models and relationships;
- seeders;
- console commands;
- services;
- validation;
- caching;
- tests;
- asset building with Vite;
- PWA/web app concerns.

---

## 3. Scope

### 3.1 Version 1 — Geography explorer

Version 1 is successful when a user can:

1. Open a responsive map centred on the Greater Golden Horseshoe.
2. See major administrative boundaries over a normal street/road basemap.
3. Toggle major and lower-level geography layers.
4. Search an official municipality or a curated common place name.
5. Select a result and fly/zoom to it.
6. Understand its hierarchy in a detail panel.
7. See nearby geographic context and major roads on the underlying map.
8. Install the application as a PWA on a supported desktop/mobile device.

### 3.2 Explicitly out of scope for V1

Do not block V1 on:

- user accounts;
- multi-user permissions;
- customer/CRM data;
- Salesforce integration;
- route optimization;
- geocoding arbitrary street addresses;
- offline tile downloads;
- self-hosting the basemap;
- PostGIS;
- native mobile applications;
- editing official GIS geometry in the application;
- crowdsourced public editing;
- complicated admin interfaces.

---

## 4. Proposed technology stack

### Application

- **Laravel 13.x**
- PHP version supported/recommended by the selected Laravel 13 release
- Composer

### Front end

- Blade
- Vite
- CSS kept intentionally simple
- Vanilla JavaScript and/or Alpine.js for ordinary UI state

Avoid React, Vue, Svelte and Inertia initially. MapLibre already provides the most complex client-side component; a separate SPA architecture would add complexity without solving a V1 problem.

### Map

- **MapLibre GL JS 6.x**
- OSM-derived basemap/style from an appropriate provider
- Custom GeoJSON/vector layers for our geography

MapLibre documentation:
https://maplibre.org/maplibre-gl-js/docs/

### Database

- **SQLite** initially

SQLite is sufficient for curated place metadata and a single-user/local-first early product. The schema should avoid SQLite-specific shortcuts that make a future PostgreSQL migration unnecessarily painful.

### Geographic processing

Keep runtime GIS processing small.

Prefer a development/import pipeline that:

1. downloads authoritative source data;
2. filters it to the GGH;
3. transforms it to WGS84 / web-map-friendly coordinates as required;
4. simplifies polygon geometry for browser performance;
5. outputs versioned application-ready GeoJSON.

Possible tooling for the import pipeline:

- GDAL/OGR command-line tools; or
- a small Python script using GeoPandas/Shapely if that proves clearer.

This processing tooling does not need to be part of the Laravel runtime.

---

## 5. High-level architecture

```text
┌────────────────────────────────────────────────────────────┐
│                     Browser / PWA                          │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                    MapLibre                          │  │
│  │                                                      │  │
│  │  Basemap + official polygons + local-place layers   │  │
│  └──────────────────────────────────────────────────────┘  │
│             │                           │                  │
│       search/select                 layer state             │
└─────────────┼───────────────────────────┼───────────────────┘
              │                           │
              ▼                           ▼
┌────────────────────────────────────────────────────────────┐
│                       Laravel                              │
│                                                            │
│  Routes / Controllers / Blade                              │
│              │                                             │
│              ▼                                             │
│      Geography services                                    │
│              │                                             │
│      ┌───────┴──────────┐                                  │
│      ▼                  ▼                                  │
│   SQLite            Static GeoJSON                         │
│   metadata          boundary files                         │
└────────────────────────────────────────────────────────────┘
```

### Responsibility split

**MapLibre owns:** rendering the map, polygons/lines/labels, zoom/pan, layer visibility, map hit-testing, selection highlighting and camera movement.

**Laravel owns:** application pages, place hierarchy and metadata, search, curated aliases, relationships between areas, source metadata, and future saved/private/business data.

**GeoJSON/static map assets own:** browser-ready official polygon geometry and stable boundary identifiers that map back to Laravel records.

---

## 6. Geographic model

A key design decision is to avoid making separate database tables for every administrative concept.

Use one hierarchical `areas` model for geographic entities.

### 6.1 `areas`

Suggested initial schema:

```text
areas
-----
id
parent_id              nullable FK -> areas.id
name
slug
area_type
administrative_status  nullable
source_identifier       nullable
source_name             nullable
geometry_key            nullable
latitude                nullable
longitude               nullable
is_ggh                  boolean
is_gta                  boolean
boundary_precision      enum/string
notes                    nullable
created_at
updated_at
```

Example `area_type` values:

```text
ggh
upper_tier
single_tier
lower_tier
community
neighbourhood
historic_area
business_district
```

Possible hierarchy:

```text
Greater Golden Horseshoe
└── York Region
    └── Vaughan
        ├── Concord
        ├── Woodbridge
        ├── Maple
        └── Kleinburg
```

Toronto:

```text
Greater Golden Horseshoe
└── Toronto
    ├── Etobicoke
    │   └── Rexdale
    ├── North York
    ├── Old Toronto
    ├── East York
    └── Scarborough
```

The exact treatment of the GTA should be decided during implementation. It may work better as a **membership/tag** rather than a strict parent because the GGH hierarchy and GTA grouping overlap conceptually.

### 6.2 `area_aliases`

```text
area_aliases
------------
id
area_id
alias
normalized_alias
alias_type
created_at
updated_at
```

Use aliases for genuinely useful alternate, historic, shorthand, or spoken names—not merely to maximize fuzzy matches.

### 6.3 `area_relationships` — defer unless needed

A strict tree will handle most initial containment. If we later need relationships such as “nearby,” “partly overlaps,” or “commonly associated with,” add an explicit relationship table then.

### 6.4 Source metadata

Every imported or curated area should be traceable to its source. At minimum, preserve source dataset/name, source identifier where available, import/update date, and whether the boundary is official, derived or approximate.

---

## 7. Geographic data strategy

### 7.1 Official municipal boundaries

Authoritative starting datasets:

1. Ontario Municipal Boundary — Upper Tier and District
2. Ontario Municipal Boundary — Lower and Single Tier

Initial pipeline:

```text
Ontario source data
        ↓
Download/import script
        ↓
Filter GGH-relevant features
        ↓
Normalize identifiers/names
        ↓
Reproject if necessary
        ↓
Simplify geometry
        ↓
GeoJSON
        ↓
public/geo/
```

Suggested generated files:

```text
public/geo/
├── ggh-boundary.geojson
├── upper-single-tier.geojson
└── lower-tier.geojson
```

Do not commit giant raw provincial source packages if they are unnecessary. Prefer documenting the source and providing a reproducible import command.

### 7.2 GGH membership

The GGH includes these 21 upper-/single-tier municipalities in the provincial planning definition:

- Region of Niagara
- Haldimand County
- City of Hamilton
- County of Brant
- City of Brantford
- Region of Waterloo
- County of Wellington
- City of Guelph
- Region of Halton
- County of Dufferin
- Region of Peel
- County of Simcoe
- City of Barrie
- City of Orillia
- Region of York
- City of Toronto
- Region of Durham
- City of Kawartha Lakes
- County of Peterborough
- City of Peterborough
- County of Northumberland

This list should be represented as application data/tests rather than only assumed from spatial position.

### 7.3 Common/local places

Official datasets will not solve the entire product problem.

A curated layer will eventually cover names people commonly use in conversation, such as Concord, Woodbridge, Maple, Kleinburg, Meadowvale, Streetsville, Dixie, Malton, Rexdale, Downsview, Ancaster, Stoney Creek and Dundas.

For every such area, classify boundary quality:

```text
official      authoritative published boundary
recognized    published planning/neighbourhood boundary
approximate   useful approximate representation
point_only    label/centroid only; no defensible polygon
```

The UI should communicate this distinction where relevant.

### 7.4 Basemap

The application should use an OSM-derived basemap through a provider whose terms allow the intended traffic and usage.

Do not make production depend on unrestricted use of the public `tile.openstreetmap.org` community servers.

The basemap provider should be abstracted through map configuration so it can be replaced without rewriting application logic.

---

## 8. Map behaviour

### 8.1 Initial camera

On first load, fit enough of the Greater Golden Horseshoe to make its shape understandable. Do not open at street-level Toronto by default.

### 8.2 Layer groups

Initial layer control:

```text
GEOGRAPHY
☑ Major boundaries
☑ Municipalities
☑ Place labels

CONTEXT
☑ Major roads / basemap
```

Future:

```text
BUSINESS
☐ Industrial areas
☐ Business parks

SALES
☐ Customers
☐ Prospects
☐ Revenue
☐ Opportunities
```

### 8.3 Zoom-dependent detail

Suggested behaviour to tune during implementation:

- **Low zoom:** GGH and upper/single-tier boundaries
- **Medium zoom:** lower-tier municipalities and their names
- **High zoom:** common local areas/community labels

Do not rely solely on zoom; explicit layer toggles should remain available.

### 8.4 Selection

Clicking a polygon or search result should:

1. select the area;
2. visually emphasize it;
3. retain useful surrounding context;
4. update the detail panel;
5. optionally fit/fly the map to a sensible view;
6. preserve parent area outlines where useful.

Example selected state:

```text
CONCORD
Community

Vaughan
York Region
GTA
Greater Golden Horseshoe

Nearby
Woodbridge · Maple · Richmond Hill · North York
```

Major-road context should normally come from the basemap rather than duplicating every road into Laravel.

---

## 9. Search

Search is a core feature, not an afterthought.

### 9.1 V1 search targets

Search official area names, common-place names and aliases.

### 9.2 Behaviour

Example query:

```text
concord
```

Result:

```text
Concord
Community in Vaughan, York Region
```

Selecting it should fly to its mapped location and show its hierarchy.

### 9.3 Search implementation

Start with SQLite-backed normalized text search. The GGH dataset is small enough that Elasticsearch/Algolia/Meilisearch are unnecessary.

Suggested ranking:

1. exact normalized name;
2. exact alias;
3. prefix match;
4. contains match.

Fuzzy matching can be added only if real usage proves spelling variation is a problem.

### 9.4 Deep links

Design routes so selected places can eventually be linked/bookmarked, for example:

```text
/areas/concord
/areas/york-region
```

The primary map screen can still remain a single route.

---

## 10. UI direction

The visual direction should be utilitarian and cartographic.

Avoid dashboard-card overload, giant rounded cards, excessive shadows, decorative gradients, oversized marketing headings, and UI that competes with the map.

Prefer a map that fills the working area, restrained header/search, compact layer control, right-side detail panel on desktop, bottom-sheet treatment on narrow screens, clear type hierarchy, consistent legend and strong selected-state styling.

### Desktop concept

```text
┌─────────────────────────────────────────────────────────────────────┐
│ TerritoryAtlas     [ Search places…                     ]  Layers   │
├───────────────────────────────────────────────────┬─────────────────┤
│                                                   │                 │
│                                                   │   CONCORD       │
│                                                   │   Community     │
│                   MAP                             │                 │
│                                                   │   Vaughan       │
│                                                   │   York Region   │
│                                                   │   GTA · GGH     │
│                                                   │                 │
│                                                   │   Nearby …      │
│                                                   │                 │
└───────────────────────────────────────────────────┴─────────────────┘
```

### Mobile/PWA concept

- map occupies most of the screen;
- search stays easy to reach;
- details appear in a dismissible bottom panel;
- layer selector opens as a compact sheet/menu.

---

## 11. PWA plan

PWA support should come **after the core map experience works**, not before.

Initial PWA scope:

- valid web app manifest;
- icons;
- standalone display mode;
- installable over HTTPS;
- sensible theme/background metadata;
- basic application-shell caching where appropriate.

Do not promise full offline mapping in V1. Offline maps require explicit tile/provider/storage decisions and should be treated as a separate feature.

---

## 12. Proposed repository structure

Target structure after initial Laravel setup:

```text
TerritoryAtlas/
├── app/
│   ├── Console/
│   │   └── Commands/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── MapController.php
│   │       ├── AreaController.php
│   │       └── AreaSearchController.php
│   ├── Models/
│   │   ├── Area.php
│   │   └── AreaAlias.php
│   └── Services/
│       └── Geography/
│           ├── AreaHierarchy.php
│           └── AreaSearch.php
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── data/
│       └── areas/
│
├── resources/
│   ├── css/
│   ├── js/
│   │   └── map/
│   │       ├── create-map.js
│   │       ├── layers.js
│   │       ├── selection.js
│   │       └── search.js
│   └── views/
│       └── map.blade.php
│
├── public/
│   └── geo/
│       ├── ggh-boundary.geojson
│       ├── upper-single-tier.geojson
│       └── lower-tier.geojson
│
├── scripts/
│   └── geography/
│       ├── README.md
│       └── ...reproducible import tooling
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
├── PLAN.md
└── README.md
```

This is a target, not a requirement to create empty folders prematurely.

---

## 13. Delivery roadmap

Build in small, reviewable PRs. Each PR should leave `main` in a working state.

### PR 0 — Bootstrap Laravel ✅ Complete

**Goal:** Establish the application foundation with no product complexity yet.

**Work**
- Install current Laravel 13 application.
- Configure SQLite for local development.
- Confirm Vite asset build works.
- Add basic test/format/lint commands.
- Add minimal README with setup instructions.
- Set application name to TerritoryAtlas.
- Establish `.env.example` without committing secrets.

**Acceptance criteria**
- Fresh clone can be installed from documented steps.
- Application boots locally.
- Default automated tests pass.
- Database can be created/migrated.

### PR 1 — First real map ✅ Complete

**Goal:** Replace the Laravel welcome screen with a usable MapLibre map centred on the GGH.

**Work**
- Install MapLibre GL JS through npm.
- Create `map.blade.php`.
- Add a full-working-area map container.
- Configure basemap through environment/config rather than scattering provider URLs in JS.
- Fit initial camera to the GGH.
- Add attribution correctly.
- Keep layout responsive.

**Acceptance criteria**
- `/` opens a zoomable/pannable real map.
- GGH is visible at useful initial scale.
- Map works after production asset build.
- No custom boundaries yet.

### PR 2 — Reproducible Ontario GIS import ✅ Complete

**Goal:** Establish the authoritative boundary-data pipeline.

**Work**
- Document Ontario source datasets and licence.
- Add reproducible import/processing tooling under `scripts/geography`.
- Download/read upper-tier and lower/single-tier data.
- Filter to GGH-relevant areas.
- Normalize feature properties.
- Convert to browser-ready GeoJSON.
- Simplify geometry while preserving useful boundary shape.
- Generate deterministic output under `public/geo`.

**Acceptance criteria**
- A documented command regenerates the GeoJSON from source.
- Generated files contain only relevant features.
- Each feature has a stable application-facing identifier/name.
- Assets are reasonably sized for browser delivery.
- Source and licence are documented.

### PR 3 — Major GGH boundaries ✅ Complete

**Goal:** Make the application teach the major geography immediately.

**Work**
- Add upper/single-tier GeoJSON as a MapLibre source.
- Add fill and outline layers.
- Create restrained colour/style system.
- Add labels at appropriate zoom.
- Add hover/click affordance.
- Highlight selected region.
- Add a compact legend.

**Acceptance criteria**
- User can visually distinguish major GGH divisions.
- Toronto, York, Peel, Durham, Halton, Hamilton, Niagara and outer-ring areas are understandable at a glance.
- Underlying roads remain readable through polygon fills.
- Selected feature is visually obvious.

### PR 4 — Lower-tier municipal layer ✅ Complete

**Goal:** Reveal the next administrative level as the user zooms in.

**Work**
- Add lower-tier GeoJSON source/layers.
- Tune zoom thresholds.
- Add lower-tier labels.
- Add layer toggle controls.
- Preserve upper-tier context when lower-tier boundaries appear.

**Acceptance criteria**
- Zooming into York reveals Vaughan, Markham, Richmond Hill, etc.
- Zooming into Peel reveals Mississauga, Brampton and Caledon.
- Layer toggles work without reloading.
- The map does not become unreadably busy.

### PR 5 — Laravel area model and hierarchy ✅ Complete

**Goal:** Move from “polygons on a map” to a geography-aware application.

**Work**
- Create `areas` migration/model.
- Create `area_aliases` migration/model.
- Implement parent/children/ancestor relationships.
- Seed the 21 GGH major areas.
- Seed lower-tier municipality metadata.
- Map GeoJSON feature IDs to `areas` records.
- Add tests for key hierarchy/membership facts.

**Required fixture/test examples**
- Vaughan belongs to York Region.
- Mississauga belongs to Peel Region.
- Toronto is single-tier.
- Barrie is separate from Simcoe County administratively.
- Orillia is separate from Simcoe County administratively.
- Guelph is separate from Wellington County administratively.
- Brantford is separate from Brant County administratively.
- Peterborough is separate from Peterborough County administratively.
- Haldimand County is single-tier despite its name.

**Acceptance criteria**
- Laravel can resolve an official map feature to a database area.
- Area hierarchy is test-covered.
- Source/administrative status is represented in data.

### PR 6 — Selection detail panel ✅ Complete

**Goal:** Answer “what is this place?” when the user clicks the map.

**Work**
- Add desktop side panel and responsive mobile treatment.
- On polygon click, show name, area type/status, parent hierarchy, GTA/GGH membership and child municipalities where useful.
- Make parent hierarchy clickable/selectable.
- Synchronize selected database area and map feature.

**Acceptance criteria**
- Clicking Vaughan shows Vaughan → York Region → GGH context.
- Clicking York Region can show its lower-tier municipalities.
- Selection survives ordinary map interaction until changed/dismissed.

### PR 7 — Search ✅ Complete

**Goal:** Make TerritoryAtlas useful when a place name is mentioned in conversation.

**Work**
- Add search input.
- Add `AreaSearchController` or equivalent endpoint.
- Search names and aliases.
- Add ranked suggestions.
- Add useful result subtitles such as `Community in Vaughan, York Region`.
- Selecting a result flies/fits the map and opens details.
- Add keyboard interaction.
- Add deep-link support if cleanly achievable here.

**Acceptance criteria**
- Search official areas quickly.
- Results disambiguate similarly named places.
- Search does not require a third-party search service.

### PR 8 — Common-place layer: first curated set ✅ Complete

**Goal:** Bridge the gap between administrative geography and the names people actually use.

**Initial curated candidates**

**Vaughan / York**
- Concord
- Woodbridge
- Maple
- Kleinburg

**Mississauga / Peel**
- Meadowvale
- Streetsville
- Dixie
- Malton

**Toronto**
- Etobicoke
- North York
- Scarborough
- Rexdale
- Downsview

**Hamilton**
- Ancaster
- Stoney Creek
- Dundas
- Flamborough

**Work**
- Research defensible sources for each area.
- Record boundary precision/status.
- Use polygons only when a reasonable published/recognized boundary exists.
- Use point/centroid labels when a polygon would imply false precision.
- Add aliases where genuinely helpful.
- Add these areas to search.
- Add appropriate high-zoom rendering.

**Acceptance criteria**
- Searching `Concord` provides useful Vaughan/York context.
- UI distinguishes official versus approximate/common geography.
- No invented “official-looking” boundary is presented as fact.

### PR 9 — Nearby/context intelligence ✅ Complete

**Goal:** Improve the answer to “what is near this place?” without overbuilding GIS infrastructure.

**Work**
- Add a simple, explainable nearby-area strategy.
- Prefer computed proximity from representative points/geometry where sensible.
- Allow curated relationships only where they provide materially better human context.
- Show nearby places in the detail panel.
- Keep highways/major roads primarily visual from the basemap.

**Acceptance criteria**
- Selected places show a short useful nearby list.
- “Nearby” does not pretend to be an exact travel-time calculation.
- No route engine is introduced.

### PR 10 — PWA ✅ Complete

**Goal:** Make TerritoryAtlas convenient to install and launch as an application.

**Work**
- Add web manifest and icons.
- Configure standalone display.
- Add basic service worker/app-shell caching where safe.
- Verify installability on supported desktop/mobile environments and document expectations.
- Handle new version/update behaviour sensibly.

**Acceptance criteria**
- Application can be installed from a supported browser.
- Installed app opens directly to the map.
- Basic shell loads gracefully under poor connectivity.
- No claim of offline basemap support.

### PR 11 — Polish and V1 release

**Goal:** Turn the accumulated functionality into a coherent first release.

**Work**
- Mobile/responsive QA.
- Keyboard/accessibility pass.
- Label-density tuning.
- Geometry/network performance review.
- Cache static GeoJSON appropriately.
- Add loading/error states.
- Add application metadata/about/source information.
- Review attribution and data licences.
- Add smoke tests for key endpoints and search.
- Document deployment.

**V1 acceptance scenario**

A user hears the word **Concord**, opens TerritoryAtlas, types `Concord`, selects it and immediately understands:

- where Concord is on a street map;
- that it is in Vaughan;
- that Vaughan is in York Region;
- that it is in the GTA and GGH;
- what major road corridors and nearby areas surround it.

If that experience is fast and obvious, V1 has accomplished its core job.

---

## 14. Testing strategy

### Unit tests

Prioritize durable domain rules: hierarchy traversal, search normalization/ranking, membership logic, boundary precision classification, and source metadata handling.

### Feature tests

Test map-page rendering, area detail endpoints/routes, search and ranking, known hierarchy examples, and invalid/missing-area behaviour.

### JavaScript/map tests

Do not try to exhaustively unit-test MapLibre itself. Test our own separable functions; add browser/E2E testing later only if regressions justify the maintenance cost.

### Data integrity tests

Examples:

- all 21 GGH major areas exist;
- every lower-tier GGH area has a valid parent where applicable;
- every geometry feature maps to a known application identifier;
- no duplicate slugs;
- official boundaries never have `approximate` precision accidentally;
- curated common areas explicitly state boundary quality.

---

## 15. Performance strategy

Start simple and measure before adding infrastructure. Early performance problems are more likely to come from geographic geometry than Laravel.

First-line controls:

- simplify polygon geometry during build/import;
- limit data to the GGH;
- serve static GeoJSON with caching/compression;
- load lower-detail layers only when needed;
- avoid repeatedly fetching unchanged geography;
- keep search data small and indexed.

Upgrade path only if needed:

```text
Static GeoJSON
      ↓
vector tile generation
      ↓
PMTiles / hosted vector tiles
      ↓
optional dedicated tile infrastructure
```

Do not jump there until measurements show the simpler approach is insufficient.

---

## 16. Security/privacy direction

V1 should contain no sensitive customer data, which keeps the security model simple.

If future sales/customer layers are added:

- treat business/account data as private by default;
- add authentication before private operational data;
- never expose customer datasets through public static GeoJSON;
- ensure API responses enforce authorization;
- avoid embedding credentials or map-provider secrets into the repository;
- distinguish public geographic data from private business overlays architecturally.

---

## 17. Future expansion path

### Industrial geography

Possible layers include industrial parks, employment lands, warehouse clusters, business improvement areas where relevant, and major logistics corridors.

### Sales territory intelligence

Possible private layers include customers, prospects, account status, revenue/GP, opportunities, visit/call recency and sales territories.

Example questions:

- Show all customers in York Region.
- Which customers are near Concord?
- Which municipalities have customers but little prospect coverage?
- Highlight accounts within a selected territory.

### Address/geocoding support

Later, an address/company location could be geocoded and the app could answer that it lies in Mississauga → Peel Region → GTA → GGH. This requires a deliberate geocoder/provider choice and should be its own feature.

### User-defined layers

Eventually: CSV import, custom points, custom territories, saved views and named layer groups.

### PostgreSQL/PostGIS

Migrate when actual requirements justify server-side spatial queries such as point-in-polygon at scale, radius searches over large datasets, spatial joins, complex territory calculations or large/private geographic datasets.

SQLite is intentionally the starting point, not a permanent ideological commitment.

---

## 18. Decisions to defer deliberately

Do not spend early development time deciding these before they matter:

- final basemap provider;
- exact production hosting platform;
- whether PostgreSQL/PostGIS is eventually required;
- exact geometry format after GeoJSON stops scaling;
- CRM/Salesforce integration;
- multi-user roles;
- native mobile packaging;
- offline map downloads;
- route planning;
- automatic third-party geocoding.

Each should become a decision when a real user story requires it.

---

## 19. Definition of “good”

TerritoryAtlas should feel like a **map that understands place names**, not GIS software that requires the user to understand GIS.

A good result is:

- fast to open;
- visually quiet;
- easy to search;
- geographically trustworthy;
- explicit when boundaries are approximate;
- useful without customer data;
- increasingly powerful as optional layers are added;
- architecturally simple enough to understand end-to-end.

The product should help build a mental model of the region through repeated use.

---

## 20. Immediate next step

PRs 0–10 are complete. Start with **PR 11 — Polish and V1 release**.

The next release should focus on responsive QA, accessibility, loading/error states, performance, attribution, source information and deployment documentation before the V1 release.
