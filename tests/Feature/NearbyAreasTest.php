<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Services\Geography\NearbyAreas;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NearbyAreasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_excludes_hierarchy_and_missing_coordinates_with_stable_tie_ordering(): void
    {
        $ancestor = Area::factory()->create([
            'name' => 'Ancestor',
            'slug' => 'ancestor',
            'geometry_key' => 'ancestor',
            'latitude' => 44,
            'longitude' => -79,
        ]);
        $selected = Area::factory()->for($ancestor, 'parent')->create([
            'name' => 'Selected',
            'slug' => 'selected',
            'geometry_key' => 'selected',
            'latitude' => 43,
            'longitude' => -79,
        ]);
        $child = Area::factory()->for($selected, 'parent')->create([
            'name' => 'Child',
            'slug' => 'child',
            'geometry_key' => 'child',
            'latitude' => 43,
            'longitude' => -79,
        ]);
        Area::factory()->for($child, 'parent')->create([
            'name' => 'Grandchild',
            'slug' => 'grandchild',
            'geometry_key' => 'grandchild',
            'latitude' => 43,
            'longitude' => -79,
        ]);
        Area::factory()->create([
            'name' => 'No coordinates',
            'slug' => 'no-coordinates',
            'geometry_key' => 'no-coordinates',
        ]);
        Area::factory()->create([
            'name' => 'Equal distance',
            'slug' => 'equal-distance-b',
            'geometry_key' => 'tie-b',
            'latitude' => 43.1,
            'longitude' => -79,
        ]);
        Area::factory()->create([
            'name' => 'Equal distance',
            'slug' => 'equal-distance-a',
            'geometry_key' => 'tie-a',
            'latitude' => 43.1,
            'longitude' => -79,
        ]);

        $nearby = app(NearbyAreas::class)->for($selected);

        $this->assertSame(['tie-a', 'tie-b'], $nearby->pluck('geometry_key')->all());
    }
}
