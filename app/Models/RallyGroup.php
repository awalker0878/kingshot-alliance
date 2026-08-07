<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RallyGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'occurrence_id',
        'recommended_formation_id',
        'name',
        'max_joiners',
        'notes',
        'sort_order',
    ];

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return HasMany<RallyAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RallyAssignment::class);
    }
}
