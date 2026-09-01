<?php

namespace App\Models;

use Database\Factories\AreaAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['area_id', 'alias', 'normalized_alias', 'alias_type'])]
class AreaAlias extends Model
{
    /** @use HasFactory<AreaAliasFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
