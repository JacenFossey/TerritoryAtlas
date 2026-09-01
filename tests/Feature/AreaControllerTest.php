<?php

namespace Tests\Feature;

use Database\Seeders\AreaSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AreaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_vaughan_details_include_hierarchy_and_memberships(): void
    {
        $this->seed(AreaSeeder::class);

        $response = $this->getJson(route('areas.show', ['area' => 'on-munid-19028']));

        $response->assertOk()->assertExactJson([
            'data' => [
                'geometry_key' => 'on-munid-19028',
                'name' => 'Vaughan',
                'area_type' => 'lower_tier',
                'administrative_status' => 'Lower Tier Municipality',
                'is_ggh' => true,
                'is_gta' => true,
                'boundary_precision' => 'official',
                'hierarchy' => [
                    [
                        'geometry_key' => null,
                        'name' => 'Greater Golden Horseshoe',
                        'area_type' => 'ggh',
                    ],
                    [
                        'geometry_key' => 'on-munid-19000',
                        'name' => 'York Region',
                        'area_type' => 'upper_tier',
                    ],
                ],
                'children' => [],
            ],
        ]);
    }

    public function test_york_region_details_list_its_lower_tier_municipalities(): void
    {
        $this->seed(AreaSeeder::class);

        $response = $this->getJson(route('areas.show', ['area' => 'on-munid-19000']));

        $response
            ->assertOk()
            ->assertJsonCount(9, 'data.children')
            ->assertJsonPath('data.children.0.name', 'Aurora')
            ->assertJsonFragment([
                'geometry_key' => 'on-munid-19028',
                'name' => 'Vaughan',
                'area_type' => 'lower_tier',
            ]);
    }

    public function test_returns_404_when_geometry_key_is_unknown(): void
    {
        $this->getJson(route('areas.show', ['area' => 'unknown-area']))
            ->assertNotFound();
    }
}
