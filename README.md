# TerritoryAtlas

TerritoryAtlas is a map-first geography explorer for the Greater Golden Horseshoe. It combines authoritative Ontario municipal boundaries with clearly identified common-place context; see [PLAN.md](PLAN.md) for the product and delivery plan.

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

## Production deployment

TerritoryAtlas can run on a conventional Laravel host with PHP, SQLite, and a web server rooted at `public/`. Deploy it over HTTPS so the installable-app features and service worker are available.

For each release:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Set `APP_ENV=production`, `APP_DEBUG=false`, a stable `APP_KEY`, and the production `APP_URL`. Set `MAP_STYLE_URL` and `MAP_ATTRIBUTION` when using a basemap provider other than the defaults. Ensure the PHP/web-server user can write to `storage`, `bootstrap/cache`, and the SQLite database and its parent directory. Do not expose `.env` or the database through the document root.

Serve compressed responses and retain the cache policy in `public/.htaccess` (or its equivalent in Nginx): hashed `/build/` assets cache for one year. The map requests replaceable geography through Laravel's `/geography/` endpoints so their one-hour cache policy and stale revalidation also work on Laravel Cloud, which does not use the Apache configuration. After deployment, verify `/`, `/manifest.webmanifest`, `/sw.js`, all three `/geography/` endpoints, search, and one area-detail response. Confirm the browser reports a registered service worker over HTTPS.

### Laravel Cloud

The initial production environment is deployed at <https://territoryatlas-production-ah4co2.laravel.cloud/>. Attach a managed MySQL database to the production environment; do not use SQLite on Laravel Cloud's ephemeral application filesystem. Laravel Cloud injects the attached database credentials automatically.

Configure a stable `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, and the production `APP_URL` in the environment settings. Keep the build command configured to install production dependencies and run `npm run build`. Use this deploy command so each release applies the schema and idempotent geography seed data before going live:

```bash
php artisan migrate --force && php artisan db:seed --force
```

Use `/up` as the environment health endpoint. Run queue workers, Redis, or object storage only if future features require them; V1 does not.

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
