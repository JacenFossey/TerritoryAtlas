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
                <div class="app-brand">
                    <h1>{{ config('app.name') }}</h1>
                    <p>Greater Golden Horseshoe</p>
                </div>

                <div class="area-search" data-area-search>
                    <label class="sr-only" for="area-search-input">Search places</label>
                    <input
                        id="area-search-input"
                        class="area-search-input"
                        type="search"
                        role="combobox"
                        placeholder="Search places…"
                        maxlength="50"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="area-search-results"
                        aria-expanded="false"
                        data-area-search-input
                    >
                    <ul
                        id="area-search-results"
                        class="area-search-results"
                        role="listbox"
                        data-area-search-results
                        hidden
                    ></ul>
                    <p class="area-search-feedback" data-area-search-feedback aria-live="polite"></p>
                </div>
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
                    <h2>Map layers</h2>
                    <fieldset class="legend-layers">
                        <legend class="sr-only">Map layers</legend>
                        <label><input type="checkbox" data-layer-toggle="major" checked> Major divisions</label>
                        <label><input type="checkbox" data-layer-toggle="municipal" checked> Lower-tier municipalities</label>
                        <label><input type="checkbox" data-layer-toggle="common" checked> Common places</label>
                    </fieldset>
                    <h2 class="legend-key-heading">Major divisions</h2>
                    <ul>
                        <li><span class="legend-swatch legend-swatch-upper" aria-hidden="true"></span>Upper-tier</li>
                        <li><span class="legend-swatch legend-swatch-single" aria-hidden="true"></span>Single-tier</li>
                        <li><span class="legend-swatch legend-swatch-lower" aria-hidden="true"></span>Lower-tier</li>
                        <li><span class="legend-point" aria-hidden="true"></span>Common place (representative point)</li>
                        <li><span class="legend-swatch legend-swatch-selected" aria-hidden="true"></span>Selected</li>
                    </ul>
                    <label class="legend-selection-label" for="region-select">Explore a division</label>
                    <select id="region-select" class="legend-selection" data-region-select disabled>
                        <option value="">Select a division</option>
                    </select>
                    <p class="legend-selection-status" data-region-select-status aria-live="polite">Loading divisions…</p>
                    <p class="sr-only" data-selected-region aria-live="polite"></p>
                </aside>

                <aside class="area-details" data-area-details aria-labelledby="area-details-name" hidden>
                    <div class="area-details-header">
                        <div>
                            <p class="area-details-type" data-area-type></p>
                            <h2 id="area-details-name" data-area-name tabindex="-1"></h2>
                        </div>
                        <button class="area-details-close" type="button" data-area-details-close aria-label="Close area details">×</button>
                    </div>

                    <p class="area-details-feedback" data-area-feedback aria-live="polite"></p>

                    <div class="area-details-content" data-area-content hidden>
                        <p class="area-details-status" data-area-status></p>
                        <p class="area-details-precision" data-area-precision></p>
                        <p class="area-details-source" data-area-source></p>

                        <section class="area-details-section" aria-labelledby="area-context-heading">
                            <h3 id="area-context-heading">Context</h3>
                            <ul class="area-details-memberships" data-area-memberships></ul>
                        </section>

                        <nav class="area-details-section" aria-labelledby="area-hierarchy-heading">
                            <h3 id="area-hierarchy-heading">Hierarchy</h3>
                            <ol class="area-details-hierarchy" data-area-hierarchy></ol>
                        </nav>

                        <section class="area-details-section" data-area-children-section aria-labelledby="area-children-heading" hidden>
                            <h3 id="area-children-heading">Contained places</h3>
                            <ul class="area-details-children" data-area-children></ul>
                        </section>
                    </div>
                </aside>
            </div>
        </main>

        <script id="map-config" type="application/json">{!! json_encode([
            'styleUrl' => config('map.style_url'),
            'attribution' => config('map.attribution'),
            'majorBoundariesUrl' => config('map.major_boundaries_url'),
            'lowerBoundariesUrl' => config('map.lower_boundaries_url'),
            'commonPlacesUrl' => config('map.common_places_url'),
            'areaDetailsUrl' => route('areas.show', ['area' => '__GEOMETRY_KEY__']),
            'areaSearchUrl' => route('areas.search'),
            'bounds' => [
                config('map.initial_bounds.southwest'),
                config('map.initial_bounds.northeast'),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </body>
</html>
