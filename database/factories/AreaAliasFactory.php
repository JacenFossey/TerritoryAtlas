<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\AreaAlias;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AreaAlias>
 */
class AreaAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = fake()->unique()->words(2, true);

        return [
            'area_id' => Area::factory(),
            'alias' => Str::title($alias),
            'normalized_alias' => Str::lower($alias),
            'alias_type' => 'alternate',
        ];
    }
}
