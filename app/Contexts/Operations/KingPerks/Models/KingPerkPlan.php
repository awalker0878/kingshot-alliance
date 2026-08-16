<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Models;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property KingPerkPlanStatus $status
 * @property Carbon $window_starts_at
 * @property Carbon $window_ends_at
 * @property Carbon|null $published_at
 * @property-read Event|null $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read Kingdom|null $kingdom
 * @property-read Player|null $createdByPlayer
 */
final class KingPerkPlan extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'occurrence_id', 'kingdom_id', 'status', 'window_starts_at', 'window_ends_at',
        'created_by_player_id', 'published_by_player_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => KingPerkPlanStatus::class,
            'window_starts_at' => 'datetime',
            'window_ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function createdByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    /** @return HasMany<KingPerkAppointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(KingPerkAppointment::class, 'plan_id')->orderBy('starts_at');
    }

    /** @return HasMany<KingPerkPositionBlock, $this> */
    public function positionBlocks(): HasMany
    {
        return $this->hasMany(KingPerkPositionBlock::class, 'plan_id')->orderBy('starts_at');
    }

    /** @return HasMany<KingSkillPlan, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(KingSkillPlan::class, 'plan_id')->orderBy('planned_activation_at');
    }

    /** @return HasMany<KingPerkRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(KingPerkRequest::class, 'plan_id')->orderByDesc('planned_speedup_minutes')->orderBy('availability_starts_at');
    }
}
