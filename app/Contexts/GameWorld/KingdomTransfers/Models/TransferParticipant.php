<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\Players\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string|null $transfer_cohort_id
 * @property TransferDirection $direction
 * @property TransferReadinessState $readiness_state
 * @property string|null $roster_entry_id
 * @property string $player_id
 * @property string $observed_name
 * @property string|null $game_player_id
 * @property string|null $source_kingdom_id
 * @property string|null $destination_kingdom_id
 * @property string|null $manager_notes
 * @property CarbonImmutable|null $withdrawn_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TransferPlan $plan
 * @property-read TransferCohort|null $cohort
 * @property-read Player $player
 * @property-read Kingdom|null $sourceKingdom
 * @property-read Kingdom|null $destinationKingdom
 * @property-read Collection<int, TransferBlocker> $blockers
 * @property-read Collection<int, TransferReadinessTransition> $readinessTransitions
 * @property-read Collection<int, TransferObservation> $observations
 * @property-read TransferCompletion|null $completion
 */
final class TransferParticipant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'readiness_state' => TransferReadinessState::class,
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TransferPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransferPlan::class, 'transfer_plan_id');
    }

    /** @return BelongsTo<TransferCohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(TransferCohort::class, 'transfer_cohort_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
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

    /** @return HasMany<TransferObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(TransferObservation::class, 'transfer_participant_id');
    }

    /** @return HasOne<TransferCompletion, $this> */
    public function completion(): HasOne
    {
        return $this->hasOne(TransferCompletion::class, 'transfer_participant_id');
    }
}
