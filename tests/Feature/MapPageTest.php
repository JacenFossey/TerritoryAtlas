<?php

namespace Tests\Feature;

use Tests\TestCase;

class MapPageTest extends TestCase
{
    public function test_the_home_page_displays_the_configured_map(): void
    {
        config()->set('map.style_url', 'https://maps.example.test/style.json');
        config()->set('map.attribution', 'Example map attribution');
        config()->set('map.major_boundaries_url', '/geo/example-major-boundaries.geojson');
        config()->set('map.lower_boundaries_url', '/geo/example-lower-boundaries.geojson');

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
            ->assertSee('data-layer-toggle="major"', false)
            ->assertSee('data-layer-toggle="municipal"', false)
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
            ->assertSee('Municipalities')
            ->assertSee('areas\/__GEOMETRY_KEY__', false)
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }
}
