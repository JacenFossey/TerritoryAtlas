<?php

namespace Tests\Feature;

use Database\Seeders\AreaSeeder;
use Database\Seeders\CommonPlaceSeeder;
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
                'latitude' => '43.8370300',
                'longitude' => '-79.5654300',
                'source_name' => 'CITY OF VAUGHAN',
                'notes' => null,
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
                'nearby' => [
                    [
                        'geometry_key' => 'on-munid-19038',
                        'name' => 'Richmond Hill',
                        'area_type' => 'lower_tier',
                    ],
                    [
                        'geometry_key' => 'on-munid-21010',
                        'name' => 'Brampton',
                        'area_type' => 'lower_tier',
                    ],
                    [
                        'geometry_key' => 'on-munid-20002',
                        'name' => 'Toronto',
                        'area_type' => 'single_tier',
                    ],
                    [
                        'geometry_key' => 'on-munid-19046',
                        'name' => 'Aurora',
                        'area_type' => 'lower_tier',
                    ],
                    [
                        'geometry_key' => 'on-munid-19049',
                        'name' => 'King',
                        'area_type' => 'lower_tier',
                    ],
                ],
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

    public function test_common_place_details_identify_a_representative_point_and_its_source(): void
    {
        $this->seed([AreaSeeder::class, CommonPlaceSeeder::class]);

        $response = $this->getJson(route('areas.show', ['area' => 'common-place-concord']));

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Concord')
            ->assertJsonPath('data.area_type', 'community')
            ->assertJsonPath('data.boundary_precision', 'point_only')
            ->assertJsonPath('data.latitude', '43.8001000')
            ->assertJsonPath('data.longitude', '-79.4819000')
            ->assertJsonPath('data.source_name', 'City of Vaughan Open Data')
            ->assertJsonPath('data.hierarchy.1.name', 'York Region')
            ->assertJsonPath('data.hierarchy.2.name', 'Vaughan')
            ->assertJsonCount(5, 'data.nearby')
            ->assertJsonPath('data.nearby.0.name', 'Maple')
            ->assertJsonPath('data.nearby.1.name', 'North York')
            ->assertJsonPath('data.nearby.2.name', 'Downsview');
    }

    public function test_returns_404_when_geometry_key_is_unknown(): void
    {
        $this->getJson(route('areas.show', ['area' => 'unknown-area']))
            ->assertNotFound();
    }
}
