<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#f7f6f2">
        <meta name="description" content="Explore municipalities and commonly used place names across Ontario's Greater Golden Horseshoe.">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

        <title>{{ config('app.name') }}</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('icons/app-icon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

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

                <details class="app-about">
                    <summary>About</summary>
                    <div class="app-about-content">
                        <h2>About TerritoryAtlas</h2>
                        <p>Explore official municipalities and commonly used place names across Ontario’s Greater Golden Horseshoe.</p>
                        <p>Municipal boundaries come from the <a href="https://data.ontario.ca/en/dataset/municipal-boundaries" target="_blank" rel="noreferrer">Province of Ontario</a> under the <a href="https://www.ontario.ca/page/open-government-licence-ontario" target="_blank" rel="noreferrer">Open Government Licence – Ontario</a>. Common places identify their source and boundary precision in the detail panel.</p>
                        <p>Basemap attribution and provider links appear in the map’s lower-right corner. TerritoryAtlas does not provide legal boundary advice or offline basemap coverage.</p>
                    </div>
                </details>
            </header>

            <div class="map-workspace">
                <div
                    id="map"
                    class="map"
                    role="region"
                    aria-label="Interactive map of the Greater Golden Horseshoe"
                >
                    <div class="map-loading" data-map-loading role="status">
                        <p data-map-loading-message>Loading map…</p>
                        <button type="button" data-map-retry hidden>Try again</button>
                    </div>
                </div>

                <details class="map-legend" data-map-legend open>
                    <summary class="map-legend-toggle">Map controls</summary>
                    <aside class="map-legend-content" aria-label="Map legend">
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
                        <p class="legend-keyboard-note">Use search or this list as a keyboard alternative to selecting map features.</p>
                        <p class="sr-only" data-selected-region aria-live="polite"></p>
                    </aside>
                </details>

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

                        <section class="area-details-section" data-area-nearby-section aria-labelledby="area-nearby-heading" hidden>
                            <h3 id="area-nearby-heading">Nearby context</h3>
                            <p class="area-details-nearby-note">Approximate proximity between representative points; not travel time.</p>
                            <ul class="area-details-nearby" data-area-nearby></ul>
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
