<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'parent_id',
    'name',
    'slug',
    'area_type',
    'administrative_status',
    'source_identifier',
    'source_name',
    'geometry_key',
    'latitude',
    'longitude',
    'is_ggh',
    'is_gta',
    'boundary_precision',
    'notes',
])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Area, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Area, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AreaAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(AreaAlias::class);
    }

    /**
     * Return ancestors in root-first order.
     *
     * @return Collection<int, Area>
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection;
        $area = $this;
        $visitedAreaIds = [$this->getKey() => true];

        while ($area->parent_id !== null) {
            $parent = $area->parent()->first();

            if ($parent === null) {
                break;
            }

            if (isset($visitedAreaIds[$parent->getKey()])) {
                throw new LogicException('A cycle was detected in the area hierarchy.');
            }

            $visitedAreaIds[$parent->getKey()] = true;
            $ancestors->prepend($parent);
            $area = $parent;
        }

        return $ancestors;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_ggh' => 'boolean',
            'is_gta' => 'boolean',
        ];
    }
}
