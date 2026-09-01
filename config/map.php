<?php

return [
    'style_url' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),

    'attribution' => env(
        'MAP_ATTRIBUTION',
        '<a href="https://openfreemap.org/" target="_blank">OpenFreeMap</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap contributors</a>',
    ),

    'major_boundaries_url' => '/geo/upper-single-tier.geojson',

    'lower_boundaries_url' => '/geo/lower-tier.geojson',

    'initial_bounds' => [
        'southwest' => [-81.15, 42.65],
        'northeast' => [-77.10, 44.95],
    ],
];
