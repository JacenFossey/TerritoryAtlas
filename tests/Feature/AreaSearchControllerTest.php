<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaAlias;
use Database\Seeders\AreaSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AreaSearchControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_ranks_exact_names_before_exact_aliases_and_prefix_matches(): void
    {
        $exactName = Area::factory()->create([
            'name' => 'Hammer',
            'slug' => 'hammer',
            'geometry_key' => 'hammer',
        ]);
        $exactAlias = Area::factory()->create([
            'name' => 'Hamilton',
            'slug' => 'hamilton',
            'geometry_key' => 'hamilton',
        ]);
        AreaAlias::factory()->for($exactAlias)->create([
            'alias' => 'Hammer',
            'normalized_alias' => 'hammer',
        ]);
        Area::factory()->create([
            'name' => 'Hammer Creek',
            'slug' => 'hammer-creek',
            'geometry_key' => 'hammer-creek',
        ]);

        $response = $this->getJson(route('areas.search', ['q' => '  HAMMER  ']));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', $exactName->name)
            ->assertJsonPath('data.1.name', $exactAlias->name)
            ->assertJsonPath('data.2.name', 'Hammer Creek');
    }

    public function test_search_returns_context_needed_to_select_and_describe_an_area(): void
    {
        $this->seed(AreaSeeder::class);

        $response = $this->getJson(route('areas.search', ['q' => 'vaugh']));

        $response->assertExactJson([
            'data' => [
                [
                    'geometry_key' => 'on-munid-19028',
                    'name' => 'Vaughan',
                    'slug' => 'vaughan',
                    'area_type' => 'lower_tier',
                    'subtitle' => 'Lower-tier municipality in York Region',
                ],
            ],
        ]);
    }

    public function test_search_treats_sql_wildcards_as_literal_characters(): void
    {
        Area::factory()->create([
            'name' => '100% Place',
            'slug' => '100-percent-place',
            'geometry_key' => '100-percent-place',
        ]);
        Area::factory()->create([
            'name' => 'Ordinary Place',
            'slug' => 'ordinary-place',
            'geometry_key' => 'ordinary-place',
        ]);

        $this->getJson(route('areas.search', ['q' => '%']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', '100% Place');
    }

    public function test_search_limits_broad_queries_to_eight_results(): void
    {
        foreach (range(1, 12) as $number) {
            Area::factory()->create([
                'name' => "Shared Place {$number}",
                'slug' => "shared-place-{$number}",
                'geometry_key' => "shared-place-{$number}",
            ]);
        }

        $this->getJson(route('areas.search', ['q' => 'shared']))
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    public function test_blank_search_returns_no_results(): void
    {
        $this->getJson(route('areas.search', ['q' => '   ']))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_search_rejects_queries_longer_than_fifty_characters(): void
    {
        $this->getJson(route('areas.search', ['q' => str_repeat('a', 51)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }
}
