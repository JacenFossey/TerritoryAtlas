<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f7f6f2">

        <title>{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="map-shell">
            <header class="app-header">
                <h1>{{ config('app.name') }}</h1>
                <p>Greater Golden Horseshoe</p>
            </header>

            <div class="map-workspace">
                <div
                    id="map"
                    class="map"
                    role="region"
                    aria-label="Interactive map of the Greater Golden Horseshoe"
                >
                    <p class="map-loading" data-map-loading>Loading map…</p>
                </div>

                <aside class="map-legend" aria-label="Map legend">
                    <h2>Major divisions</h2>
                    <ul>
                        <li><span class="legend-swatch legend-swatch-upper" aria-hidden="true"></span>Upper-tier</li>
                        <li><span class="legend-swatch legend-swatch-single" aria-hidden="true"></span>Single-tier</li>
                        <li><span class="legend-swatch legend-swatch-selected" aria-hidden="true"></span>Selected</li>
                    </ul>
                    <label class="legend-selection-label" for="region-select">Explore a division</label>
                    <select id="region-select" class="legend-selection" data-region-select disabled>
                        <option value="">Select a division</option>
                    </select>
                    <p class="sr-only" data-selected-region aria-live="polite"></p>
                </aside>
            </div>
        </main>

        <script id="map-config" type="application/json">{!! json_encode([
            'styleUrl' => config('map.style_url'),
            'attribution' => config('map.attribution'),
            'majorBoundariesUrl' => config('map.major_boundaries_url'),
            'bounds' => [
                config('map.initial_bounds.southwest'),
                config('map.initial_bounds.northeast'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </body>
</html>
