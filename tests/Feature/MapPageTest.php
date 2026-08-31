<?php

namespace Tests\Feature;

use Tests\TestCase;

class MapPageTest extends TestCase
{
    public function test_the_home_page_displays_the_configured_map(): void
    {
        config()->set('map.style_url', 'https://maps.example.test/style.json');
        config()->set('map.attribution', 'Example map attribution');

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertViewIs('map')
            ->assertSee('id="map"', false)
            ->assertSee('Interactive map of the Greater Golden Horseshoe')
            ->assertSee('maps.example.test')
            ->assertSee('Example map attribution')
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }
}
