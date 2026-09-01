<?php

namespace Tests\Feature;

use Tests\TestCase;

class GeographyAssetControllerTest extends TestCase
{
    public function test_geography_assets_have_a_shared_production_cache_policy(): void
    {
        foreach (['upper-single-tier.geojson', 'lower-tier.geojson', 'common-places.geojson'] as $filename) {
            $response = $this->get("/geography/{$filename}");

            $response
                ->assertOk()
                ->assertHeader('Content-Type', 'application/geo+json')
                ->assertHeader('Cache-Control', 'max-age=3600, public, stale-while-revalidate=86400');
        }
    }

    public function test_unrecognized_geography_assets_are_not_served(): void
    {
        $this->get('/geography/database.sqlite')->assertNotFound();
    }
}
