<?php

namespace Tests\Feature;

use App\Models\Area;
use Database\Seeders\AreaSeeder;
use Database\Seeders\CommonPlaceSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommonPlaceSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_the_first_curated_common_places_as_representative_points(): void
    {
        $this->seed([AreaSeeder::class, CommonPlaceSeeder::class]);

        $commonPlaces = Area::query()
            ->where('geometry_key', 'like', 'common-place-%')
            ->orderBy('name')
            ->get();

        $this->assertSame(17, $commonPlaces->count());
        $this->assertSame([
            'Ancaster',
            'Concord',
            'Dixie',
            'Downsview',
            'Dundas',
            'Etobicoke',
            'Flamborough',
            'Kleinburg',
            'Malton',
            'Maple',
            'Meadowvale',
            'North York',
            'Rexdale',
            'Scarborough',
            'Stoney Creek',
            'Streetsville',
            'Woodbridge',
        ], $commonPlaces->pluck('name')->all());
        $this->assertTrue($commonPlaces->every(
            fn (Area $area): bool => $area->boundary_precision === 'point_only'
                && $area->latitude !== null
                && $area->longitude !== null
                && $area->source_name !== null
                && str_contains($area->notes ?? '', 'no boundary is asserted'),
        ));
    }

    public function test_concord_belongs_to_vaughan_and_inherits_regional_memberships(): void
    {
        $this->seed([AreaSeeder::class, CommonPlaceSeeder::class]);

        $concord = Area::query()->where('geometry_key', 'common-place-concord')->firstOrFail();

        $this->assertSame('Vaughan', $concord->parent->name);
        $this->assertSame(
            ['Greater Golden Horseshoe', 'York Region', 'Vaughan'],
            $concord->ancestors()->pluck('name')->all(),
        );
        $this->assertTrue($concord->is_ggh);
        $this->assertTrue($concord->is_gta);
    }

    public function test_reseeding_common_places_is_idempotent(): void
    {
        $this->seed([AreaSeeder::class, CommonPlaceSeeder::class]);

        $this->seed(CommonPlaceSeeder::class);

        $this->assertSame(
            17,
            Area::query()->where('geometry_key', 'like', 'common-place-%')->count(),
        );
    }
}
