<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaAlias;
use Database\Seeders\AreaSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AreaHierarchyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeded_areas_match_the_committed_boundary_assets(): void
    {
        $this->seed(AreaSeeder::class);

        $this->assertSame(111, Area::query()->count());
        $this->assertSame(21, Area::query()->whereIn('area_type', ['upper_tier', 'single_tier'])->count());
        $this->assertSame(89, Area::query()->where('area_type', 'lower_tier')->count());
        $this->assertSame(110, Area::query()->whereNotNull('latitude')->whereNotNull('longitude')->count());

        $york = Area::query()->where('geometry_key', 'on-munid-19000')->sole();
        $vaughan = Area::query()->where('geometry_key', 'on-munid-19028')->sole();

        $this->assertSame('York Region', $york->name);
        $this->assertSame('19000', $york->source_identifier);
        $this->assertSame('Vaughan', $vaughan->name);
        $this->assertSame('York Region', $vaughan->parent->name);
    }

    public function test_reseeding_is_idempotent_and_removes_stale_official_areas(): void
    {
        $this->seed(AreaSeeder::class);
        Area::factory()->create([
            'name' => 'Former Municipality',
            'slug' => 'former-municipality',
            'geometry_key' => 'on-munid-obsolete',
        ]);

        $this->seed(AreaSeeder::class);
        $this->seed(AreaSeeder::class);

        $this->assertSame(111, Area::query()->count());
        $this->assertFalse(Area::query()->where('geometry_key', 'on-munid-obsolete')->exists());
    }

    public function test_area_exposes_children_and_root_first_ancestors(): void
    {
        $this->seed(AreaSeeder::class);

        $york = Area::query()->where('slug', 'york-region')->sole();
        $vaughan = Area::query()->where('slug', 'vaughan')->sole();

        $this->assertTrue($york->children->contains($vaughan));
        $this->assertSame(
            ['Greater Golden Horseshoe', 'York Region'],
            $vaughan->ancestors()->pluck('name')->all(),
        );
    }

    public function test_gta_is_membership_rather_than_an_administrative_parent(): void
    {
        $this->seed(AreaSeeder::class);

        $mississauga = Area::query()->where('slug', 'mississauga')->sole();
        $toronto = Area::query()->where('slug', 'toronto')->sole();
        $hamilton = Area::query()
            ->where('slug', 'hamilton')
            ->where('area_type', 'single_tier')
            ->sole();

        $this->assertSame('Peel Region', $mississauga->parent->name);
        $this->assertTrue($mississauga->is_gta);
        $this->assertSame('single_tier', $toronto->area_type);
        $this->assertSame('Greater Golden Horseshoe', $toronto->parent->name);
        $this->assertTrue($toronto->is_gta);
        $this->assertSame('Greater Golden Horseshoe', $hamilton->parent->name);
        $this->assertFalse($hamilton->is_gta);
    }

    #[DataProvider('administrativelySeparateCities')]
    public function test_single_tier_cities_are_separate_from_surrounding_counties(
        string $citySlug,
        string $countySlug,
    ): void {
        $this->seed(AreaSeeder::class);

        $city = Area::query()->where('slug', $citySlug)->sole();
        $county = Area::query()->where('slug', $countySlug)->sole();

        $this->assertSame('single_tier', $city->area_type);
        $this->assertSame('Greater Golden Horseshoe', $city->parent->name);
        $this->assertFalse($city->is($county));
        $this->assertFalse($city->parent->is($county));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function administrativelySeparateCities(): array
    {
        return [
            'Barrie and Simcoe County' => ['barrie', 'simcoe-county'],
            'Orillia and Simcoe County' => ['orillia', 'simcoe-county'],
            'Guelph and Wellington County' => ['guelph', 'wellington-county'],
            'Brantford and Brant County' => ['brantford', 'brant-county'],
            'Peterborough and Peterborough County' => ['peterborough', 'peterborough-county'],
        ];
    }

    public function test_haldimand_county_is_single_tier_despite_its_name(): void
    {
        $this->seed(AreaSeeder::class);

        $haldimand = Area::query()->where('slug', 'haldimand-county')->sole();

        $this->assertSame('single_tier', $haldimand->area_type);
        $this->assertSame('Single Tier Municipality', $haldimand->administrative_status);
        $this->assertSame('Greater Golden Horseshoe', $haldimand->parent->name);
    }

    public function test_area_aliases_belong_to_an_area(): void
    {
        $area = Area::factory()->create();
        $alias = AreaAlias::factory()->for($area)->create([
            'alias' => 'The Hammer',
            'normalized_alias' => 'the hammer',
            'alias_type' => 'spoken',
        ]);

        $this->assertTrue($area->aliases->contains($alias));
        $this->assertTrue($alias->area->is($area));
    }
}
