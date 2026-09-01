<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Services\Geography\NearbyAreas;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

class AreaController extends Controller
{
    public function __invoke(Area $area, NearbyAreas $nearbyAreas): JsonResponse
    {
        $area->load([
            'children' => fn (HasMany $children): HasMany => $children->orderBy('name'),
        ]);

        return response()->json([
            'data' => [
                'geometry_key' => $area->geometry_key,
                'name' => $area->name,
                'area_type' => $area->area_type,
                'administrative_status' => $area->administrative_status,
                'is_ggh' => $area->is_ggh,
                'is_gta' => $area->is_gta,
                'boundary_precision' => $area->boundary_precision,
                'latitude' => $area->latitude,
                'longitude' => $area->longitude,
                'source_name' => $area->source_name,
                'notes' => $area->notes,
                'hierarchy' => $area->ancestors()
                    ->map(fn (Area $ancestor): array => $this->summary($ancestor))
                    ->values()
                    ->all(),
                'children' => $area->children
                    ->map(fn (Area $child): array => $this->summary($child))
                    ->values()
                    ->all(),
                'nearby' => $nearbyAreas->for($area)
                    ->map(fn (Area $nearby): array => $this->summary($nearby))
                    ->all(),
            ],
        ]);
    }

    /**
     * @return array{geometry_key: ?string, name: string, area_type: string}
     */
    private function summary(Area $area): array
    {
        return [
            'geometry_key' => $area->geometry_key,
            'name' => $area->name,
            'area_type' => $area->area_type,
        ];
    }
}
