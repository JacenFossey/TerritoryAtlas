# TerritoryAtlas

TerritoryAtlas is a map-first geography explorer for the Greater Golden Horseshoe. The application currently provides a responsive MapLibre basemap centred on the region; see [PLAN.md](PLAN.md) for the product and delivery plan.

## Requirements

- PHP 8.3 or newer with SQLite support
- [Composer](https://getcomposer.org/)
- Node.js 20.19.x, or Node.js 22.12 and newer, with npm

## Setup

From a fresh clone, run:

```bash
composer run setup
```

This installs PHP and JavaScript dependencies, creates `.env` from `.env.example`, generates a local application key, creates and migrates `database/database.sqlite`, seeds the geography metadata, and builds the frontend assets.

Start the local development services with:

```bash
composer run dev
```

The application is available at <http://localhost:8080> by default. Stop the development services with `Ctrl+C`.

## Installing the web app

After a production build is deployed over HTTPS, supported browsers can install TerritoryAtlas from their address-bar or browser-menu install action. Localhost is also treated as a secure context for development.

The service worker caches the application shell and same-origin static geography after it is requested. It does not cache the external basemap, search responses, or area-detail responses, so an internet connection is still required for the complete map experience. Updated service workers take over after existing TerritoryAtlas tabs and installed-app windows close; bump the cache version in `public/sw.js` whenever cached shell behavior changes.

## Common commands

```bash
composer test       # Run the automated test suite
composer run lint   # Check PHP formatting without changing files
composer run format # Apply PHP formatting
npm run dev         # Run only the Vite development server
npm run build       # Create a production asset build
php artisan migrate --seed # Apply migrations and seed geography metadata
```

## Geography data

Browser-ready GGH boundary assets are committed under `public/geo`. Regenerate and validate them with:

```bash
python3 scripts/geography/import_boundaries.py
python3 scripts/geography/import_boundaries.py --validate-only
```

See [`scripts/geography/README.md`](scripts/geography/README.md) for authoritative sources, licensing, processing decisions, and tests.

Local secrets belong in `.env`, which is ignored by Git. Keep `.env.example` safe to commit and update it whenever a new environment variable becomes required.
