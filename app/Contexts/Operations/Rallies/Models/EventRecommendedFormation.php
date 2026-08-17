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
 * @property-read RallyGuidanceRule|null $guidanceRule
 */
final class EventRecommendedFormation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['occurrence_id', 'alliance_id', 'guidance_rule_id', 'key', 'name', 'assignment_role', 'infantry_percent', 'cavalry_percent', 'archer_percent', 'heroes', 'notes', 'sort_order', 'created_by_player_id', 'updated_by_player_id'];

    protected function casts(): array
    {
        return ['infantry_percent' => 'integer', 'cavalry_percent' => 'integer', 'archer_percent' => 'integer', 'heroes' => 'array', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<EventOccurrence,$this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    /** @return BelongsTo<RallyGuidanceRule,$this> */
    public function guidanceRule(): BelongsTo
    {
        return $this->belongsTo(RallyGuidanceRule::class, 'guidance_rule_id');
    }

    /** @return HasMany<RallyGroup,$this> */
    public function rallyGroups(): HasMany
    {
        return $this->hasMany(RallyGroup::class, 'recommended_formation_id');
    }
}
