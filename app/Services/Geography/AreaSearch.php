<?php

namespace App\Services\Geography;

use App\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AreaSearch
{
    private const RESULT_LIMIT = 8;

    /**
     * @return list<array{geometry_key: ?string, name: string, slug: string, area_type: string, subtitle: string}>
     */
    public function search(string $query): array
    {
        $normalizedQuery = $this->normalize($query);

        if ($normalizedQuery === '') {
            return [];
        }

        $escapedQuery = $this->escapeLike($normalizedQuery);
        $prefixQuery = $escapedQuery.'%';
        $containsQuery = '%'.$escapedQuery.'%';
        $rankingSql = <<<'SQL'
            case
                when lower(areas.name) = ? then 0
                when exists (
                    select 1 from area_aliases
                    where area_aliases.area_id = areas.id
                    and area_aliases.normalized_alias = ?
                ) then 1
                when lower(areas.name) like ? escape '!'
                    or exists (
                        select 1 from area_aliases
                        where area_aliases.area_id = areas.id
                        and area_aliases.normalized_alias like ? escape '!'
                    ) then 2
                else 3
            end
            SQL;

        return Area::query()
            ->select('areas.*')
            ->selectRaw($rankingSql.' as search_rank', [
                $normalizedQuery,
                $normalizedQuery,
                $prefixQuery,
                $prefixQuery,
            ])
            ->with(['parent.parent.parent'])
            ->whereNotNull('geometry_key')
            ->where(function (Builder $areas) use ($containsQuery): void {
                $areas
                    ->whereRaw("lower(areas.name) like ? escape '!'", [$containsQuery])
                    ->orWhereHas('aliases', function (Builder $aliases) use ($containsQuery): void {
                        $aliases->whereRaw("normalized_alias like ? escape '!'", [$containsQuery]);
                    });
            })
            ->orderBy('search_rank')
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Area $area): array => [
                'geometry_key' => $area->geometry_key,
                'name' => $area->name,
                'slug' => $area->slug,
                'area_type' => $area->area_type,
                'subtitle' => $this->subtitle($area),
            ])
            ->all();
    }

    private function subtitle(Area $area): string
    {
        $type = match ($area->area_type) {
            'upper_tier' => 'Upper-tier municipality',
            'single_tier' => 'Single-tier municipality',
            'lower_tier' => 'Lower-tier municipality',
            'community' => 'Community',
            'neighbourhood' => 'Neighbourhood',
            'historic_area' => 'Historic area',
            'business_district' => 'Business district',
            default => Str::headline($area->area_type),
        };
        $parents = collect();
        $parent = $area->parent;

        while ($parent !== null && $parent->area_type !== 'ggh') {
            $parents->push($parent->name);
            $parent = $parent->parent;
        }

        if ($parents->isEmpty()) {
            return $type;
        }

        return $type.' in '.$parents->join(', ');
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
