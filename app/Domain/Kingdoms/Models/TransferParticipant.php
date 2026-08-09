<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string|null $transfer_group_id
 * @property TransferDirection $direction
 * @property TransferReadinessState $readiness_state
 * @property string|null $roster_entry_id
 * @property string|null $kingdom_player_id
 * @property string|null $membership_id
 * @property string $observed_name
 * @property string|null $game_player_id
 * @property string|null $source_kingdom_id
 * @property string|null $destination_kingdom_id
 * @property string|null $manager_notes
 * @property Carbon|null $withdrawn_at
 * @property-read Alliance $alliance
 * @property-read TransferPlan $plan
 * @property-read TransferGroup|null $group
 * @property-read AllianceRosterEntry|null $rosterEntry
 * @property-read KingdomPlayer|null $player
 * @property-read AllianceMembership|null $membership
 * @property-read Kingdom|null $sourceKingdom
 * @property-read Kingdom|null $destinationKingdom
 * @property-read TransferCompletion|null $completion
 */
final class TransferParticipant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'transfer_group_id',
        'direction',
        'readiness_state',
        'roster_entry_id',
        'kingdom_player_id',
        'membership_id',
        'observed_name',
        'game_player_id',
        'source_kingdom_id',
        'destination_kingdom_id',
        'manager_notes',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'readiness_state' => TransferReadinessState::class,
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<TransferPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransferPlan::class, 'transfer_plan_id');
    }

    /** @return BelongsTo<TransferGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TransferGroup::class, 'transfer_group_id');
    }

    /** @return BelongsTo<AllianceRosterEntry, $this> */
    public function rosterEntry(): BelongsTo
    {
        return $this->belongsTo(AllianceRosterEntry::class, 'roster_entry_id');
    }

    /** @return BelongsTo<KingdomPlayer, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(KingdomPlayer::class, 'kingdom_player_id');
    }

    /** @return BelongsTo<AllianceMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AllianceMembership::class, 'membership_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function sourceKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'source_kingdom_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function destinationKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'destination_kingdom_id');
    }

    /** @return HasMany<TransferBlocker, $this> */
    public function blockers(): HasMany
    {
        return $this->hasMany(TransferBlocker::class, 'transfer_participant_id');
    }

    /** @return HasMany<TransferReadinessTransition, $this> */
    public function readinessTransitions(): HasMany
    {
        return $this->hasMany(TransferReadinessTransition::class, 'transfer_participant_id');
    }

    /** @return HasOne<TransferCompletion, $this> */
    public function completion(): HasOne
    {
        return $this->hasOne(TransferCompletion::class, 'transfer_participant_id');
    }
}
