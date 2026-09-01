<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

class AreaSeeder extends Seeder
{
    private const GTA_MAJOR_GEOMETRY_KEYS = [
        'on-munid-18000', // Durham Region
        'on-munid-19000', // York Region
        'on-munid-20002', // Toronto
        'on-munid-21000', // Peel Region
        'on-munid-24000', // Halton Region
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $majorFeatures = $this->features(public_path('geo/upper-single-tier.geojson'));
        $lowerTierFeatures = $this->features(public_path('geo/lower-tier.geojson'));
        $expectedGeometryKeys = $this->geometryKeys([...$majorFeatures, ...$lowerTierFeatures]);

        DB::transaction(function () use ($expectedGeometryKeys, $lowerTierFeatures, $majorFeatures): void {
            $ggh = Area::query()->updateOrCreate(
                [
                    'parent_id' => null,
                    'slug' => 'greater-golden-horseshoe',
                ],
                [
                    'name' => 'Greater Golden Horseshoe',
                    'area_type' => 'ggh',
                    'administrative_status' => null,
                    'source_identifier' => null,
                    'source_name' => 'Ontario Growth Plan for the Greater Golden Horseshoe',
                    'geometry_key' => null,
                    'is_ggh' => true,
                    'is_gta' => false,
                    'boundary_precision' => null,
                ],
            );

            $majorAreas = [];

            foreach ($majorFeatures as $feature) {
                $properties = $feature['properties'];
                $geometryKey = $this->requiredString($properties, 'id');
                $isGta = in_array($geometryKey, self::GTA_MAJOR_GEOMETRY_KEYS, true);
                $area = $this->seedFeature($properties, $ggh, $isGta);
                $majorAreas[$geometryKey] = $area;
            }

            foreach ($lowerTierFeatures as $feature) {
                $properties = $feature['properties'];
                $parentGeometryKey = $this->requiredString($properties, 'parent_id');
                $parent = $majorAreas[$parentGeometryKey] ?? null;

                if ($parent === null) {
                    throw new UnexpectedValueException("No seeded major area matches [{$parentGeometryKey}].");
                }

                $this->seedFeature($properties, $parent, $parent->is_gta);
            }

            $this->deleteStaleOfficialAreas($expectedGeometryKeys);
        });
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function seedFeature(array $properties, Area $parent, bool $isGta): Area
    {
        $geometryKey = $this->requiredString($properties, 'id');

        return Area::query()->updateOrCreate(
            ['geometry_key' => $geometryKey],
            [
                'parent_id' => $parent->getKey(),
                'name' => $this->requiredString($properties, 'name'),
                'slug' => $this->requiredString($properties, 'slug'),
                'area_type' => $this->requiredString($properties, 'area_type'),
                'administrative_status' => $this->requiredString($properties, 'administrative_status'),
                'source_identifier' => $this->requiredString($properties, 'source_identifier'),
                'source_name' => $this->requiredString($properties, 'source_name'),
                'is_ggh' => (bool) ($properties['is_ggh'] ?? false),
                'is_gta' => $isGta,
                'boundary_precision' => $this->requiredString($properties, 'boundary_precision'),
            ],
        );
    }

    /**
     * @param  list<string>  $expectedGeometryKeys
     */
    private function deleteStaleOfficialAreas(array $expectedGeometryKeys): void
    {
        $staleOfficialAreas = Area::query()
            ->where('geometry_key', 'like', 'on-munid-%')
            ->whereNotIn('geometry_key', $expectedGeometryKeys);

        (clone $staleOfficialAreas)
            ->where('area_type', 'lower_tier')
            ->delete();

        $staleOfficialAreas->delete();
    }

    /**
     * @param  list<array{properties: array<string, mixed>}>  $features
     * @return list<string>
     */
    private function geometryKeys(array $features): array
    {
        return array_map(
            fn (array $feature): string => $this->requiredString($feature['properties'], 'id'),
            $features,
        );
    }

    /**
     * @return list<array{properties: array<string, mixed>}>
     *
     * @throws JsonException
     */
    private function features(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read boundary metadata from [{$path}].");
        }

        $collection = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $features = $collection['features'] ?? null;

        if (! is_array($features)) {
            throw new UnexpectedValueException("Boundary metadata in [{$path}] has no feature collection.");
        }

        return $features;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function requiredString(array $properties, string $key): string
    {
        $value = $properties[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException("Boundary feature property [{$key}] must be a non-empty string.");
        }

        return $value;
    }
}
