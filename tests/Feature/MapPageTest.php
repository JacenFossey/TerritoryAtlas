<?php

namespace Tests\Feature;

use Tests\TestCase;

class MapPageTest extends TestCase
{
    public function test_the_home_page_advertises_the_installable_application(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.webmanifest')
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('icons/apple-touch-icon.png')
            ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
            ->assertSee('<meta name="apple-mobile-web-app-status-bar-style" content="default">', false)
            ->assertSee('<meta name="description"', false);
    }

    public function test_the_home_page_displays_the_configured_map(): void
    {
        config()->set('map.style_url', 'https://maps.example.test/style.json');
        config()->set('map.attribution', 'Example map attribution');
        config()->set('map.major_boundaries_url', '/geo/example-major-boundaries.geojson');
        config()->set('map.lower_boundaries_url', '/geo/example-lower-boundaries.geojson');
        config()->set('map.common_places_url', '/geo/example-common-places.geojson');

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertViewIs('map')
            ->assertSee('id="map"', false)
            ->assertSee('Interactive map of the Greater Golden Horseshoe')
            ->assertSee('maps.example.test')
            ->assertSee('Example map attribution')
            ->assertSee('example-major-boundaries.geojson')
            ->assertSee('example-lower-boundaries.geojson')
            ->assertSee('example-common-places.geojson')
            ->assertSee('data-area-search-input', false)
            ->assertSee('Search places…')
            ->assertSee('area-search', false)
            ->assertSee('About TerritoryAtlas')
            ->assertSee('Open Government Licence – Ontario')
            ->assertSee('data-map-retry', false)
            ->assertSee('Use search or this list as a keyboard alternative to selecting map features.')
            ->assertSee('data-map-legend', false)
            ->assertSee('Map controls')
            ->assertSee('data-layer-toggle="major"', false)
            ->assertSee('data-layer-toggle="municipal"', false)
            ->assertSee('data-layer-toggle="common"', false)
            ->assertSee('Major divisions')
            ->assertSee('Lower-tier municipalities')
            ->assertSee('Upper-tier')
            ->assertSee('Single-tier')
            ->assertSee('Lower-tier')
            ->assertSee('Explore a division')
            ->assertSee('Select a division')
            ->assertSee('data-area-details', false)
            ->assertSee('Close area details')
            ->assertSee('Hierarchy')
            ->assertSee('Contained places')
            ->assertSee('Nearby context')
            ->assertSee('Approximate proximity between representative points; not travel time.')
            ->assertSee('areas\/__GEOMETRY_KEY__', false)
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }
}
