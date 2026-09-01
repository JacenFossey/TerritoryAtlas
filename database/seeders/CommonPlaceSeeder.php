<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use UnexpectedValueException;

class CommonPlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = $this->features(public_path('geo/common-places.geojson'));
        $expectedGeometryKeys = array_map(
            fn (array $feature): string => $this->requiredString($feature['properties'], 'id'),
            $features,
        );

        DB::transaction(function () use ($expectedGeometryKeys, $features): void {
            foreach ($features as $feature) {
                $properties = $feature['properties'];
                $geometryKey = $this->requiredString($properties, 'id');
                $parentGeometryKey = $this->requiredString($properties, 'parent_id');
                $parent = Area::query()->where('geometry_key', $parentGeometryKey)->first();

                if ($parent === null) {
                    throw new UnexpectedValueException("No seeded official area matches [{$parentGeometryKey}].");
                }

                [$longitude, $latitude] = $this->pointCoordinates($feature);
                $sourceUrl = $this->requiredString($properties, 'source_url');
                $boundaryPrecision = $this->requiredString($properties, 'boundary_precision');

                if ($boundaryPrecision !== 'point_only') {
                    throw new UnexpectedValueException("Common place [{$geometryKey}] must be classified as point_only.");
                }

                Area::query()->updateOrCreate(
                    ['geometry_key' => $geometryKey],
                    [
                        'parent_id' => $parent->getKey(),
                        'name' => $this->requiredString($properties, 'name'),
                        'slug' => $this->requiredString($properties, 'slug'),
                        'area_type' => $this->requiredString($properties, 'area_type'),
                        'administrative_status' => null,
                        'source_identifier' => null,
                        'source_name' => $this->requiredString($properties, 'source_name'),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'is_ggh' => $parent->is_ggh,
                        'is_gta' => $parent->is_gta,
                        'boundary_precision' => $boundaryPrecision,
                        'notes' => "Representative label point only; no boundary is asserted. Recognition source: {$sourceUrl}",
                    ],
                );
            }

            Area::query()
                ->where('geometry_key', 'like', 'common-place-%')
                ->whereNotIn('geometry_key', $expectedGeometryKeys)
                ->delete();
        });
    }

    /**
     * @return list<array{properties: array<string, mixed>, geometry: array<string, mixed>}>
     *
     * @throws JsonException
     */
    private function features(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read common-place metadata from [{$path}].");
        }

        $collection = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $features = $collection['features'] ?? null;

        if (! is_array($features)) {
            throw new UnexpectedValueException("Common-place metadata in [{$path}] has no feature collection.");
        }

        return $features;
    }

    /**
     * @param  array{geometry: array<string, mixed>}  $feature
     * @return array{float, float}
     */
    private function pointCoordinates(array $feature): array
    {
        $geometry = $feature['geometry'];
        $coordinates = $geometry['coordinates'] ?? null;

        if (($geometry['type'] ?? null) !== 'Point'
            || ! is_array($coordinates)
            || count($coordinates) !== 2
            || ! is_numeric($coordinates[0])
            || ! is_numeric($coordinates[1])) {
            throw new UnexpectedValueException('Every common-place feature must have one valid point geometry.');
        }

        return [(float) $coordinates[0], (float) $coordinates[1]];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function requiredString(array $properties, string $key): string
    {
        $value = $properties[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new UnexpectedValueException("Common-place feature property [{$key}] must be a non-empty string.");
        }

        return $value;
    }
}
