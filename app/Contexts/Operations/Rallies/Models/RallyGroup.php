<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Models;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read EventOccurrence $occurrence
 * @property-read EventRecommendedFormation|null $recommendedFormation
 */
final class RallyGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['occurrence_id', 'alliance_id', 'recommended_formation_id', 'name', 'max_joiners', 'notes', 'sort_order', 'created_by_player_id', 'updated_by_player_id'];

    protected function casts(): array
    {
        return ['max_joiners' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<EventOccurrence,$this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    /** @return BelongsTo<EventRecommendedFormation,$this> */
    public function recommendedFormation(): BelongsTo
    {
        return $this->belongsTo(EventRecommendedFormation::class, 'recommended_formation_id');
    }

    /** @return HasMany<RallyAssignment,$this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RallyAssignment::class);
    }
}
