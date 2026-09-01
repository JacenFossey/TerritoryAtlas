<?php

namespace App\Services\Geography;

use App\Models\Area;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class NearbyAreas
{
    private const RESULT_LIMIT = 5;

    /**
     * Find point-backed areas nearest to the selected point.
     *
     * Polygon-only areas are deliberately excluded because their geometry is
     * static browser data and a guessed centroid would imply false precision.
     * Contained and containing areas are also excluded; they are shown in the
     * hierarchy and contained-place sections instead.
     *
     * @return Collection<int, Area>
     */
    public function for(Area $area): Collection
    {
        if ($area->latitude === null || $area->longitude === null) {
            return new Collection;
        }

        $excludedIds = $area->ancestors()
            ->pluck('id')
            ->toBase()
            ->push($area->getKey())
            ->merge($this->descendantIds($area));
        $latitude = (float) $area->latitude;
        $longitude = (float) $area->longitude;

        return Area::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('id', $excludedIds)
            ->get()
            ->sortBy(fn (Area $candidate): array => [
                $this->distance($latitude, $longitude, (float) $candidate->latitude, (float) $candidate->longitude),
                $candidate->name,
                $candidate->geometry_key,
                $candidate->getKey(),
            ])
            ->take(self::RESULT_LIMIT)
            ->values();
    }

    /**
     * @return SupportCollection<int, int>
     */
    private function descendantIds(Area $area): SupportCollection
    {
        $descendantIds = collect();
        $parentIds = collect([$area->getKey()]);

        while ($parentIds->isNotEmpty()) {
            $parentIds = Area::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id');
            $descendantIds = $descendantIds->merge($parentIds);
        }

        return $descendantIds;
    }

    private function distance(float $latitude, float $longitude, float $candidateLatitude, float $candidateLongitude): float
    {
        $latitudeDelta = deg2rad($candidateLatitude - $latitude);
        $longitudeDelta = deg2rad($candidateLongitude - $longitude);
        $originLatitude = deg2rad($latitude);
        $candidateLatitude = deg2rad($candidateLatitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($originLatitude) * cos($candidateLatitude) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
