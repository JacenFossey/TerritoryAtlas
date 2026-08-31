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

This installs PHP and JavaScript dependencies, creates `.env` from `.env.example`, generates a local application key, creates and migrates `database/database.sqlite`, and builds the frontend assets.

Start the local development services with:

```bash
composer run dev
```

The application is available at <http://localhost:8080> by default. Stop the development services with `Ctrl+C`.

## Common commands

```bash
composer test       # Run the automated test suite
composer run lint   # Check PHP formatting without changing files
composer run format # Apply PHP formatting
npm run dev         # Run only the Vite development server
npm run build       # Create a production asset build
php artisan migrate # Apply pending database migrations
```

Local secrets belong in `.env`, which is ignored by Git. Keep `.env.example` safe to commit and update it whenever a new environment variable becomes required.
