<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'area_type' => 'lower_tier',
            'administrative_status' => 'Lower Tier Municipality',
            'is_ggh' => true,
            'is_gta' => false,
            'boundary_precision' => 'official',
        ];
    }
}
