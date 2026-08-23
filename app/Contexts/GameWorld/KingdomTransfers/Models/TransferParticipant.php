<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class TransferParticipant extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['direction' => TransferDirection::class, 'readiness_state' => TransferReadinessState::class, 'withdrawn_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransferPlan::class, 'transfer_plan_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(TransferCohort::class, 'transfer_cohort_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function sourceKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'source_kingdom_id');
    }

    public function destinationKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'destination_kingdom_id');
    }

    public function blockers(): HasMany
    {
        return $this->hasMany(TransferBlocker::class, 'transfer_participant_id');
    }

    public function readinessTransitions(): HasMany
    {
        return $this->hasMany(TransferReadinessTransition::class, 'transfer_participant_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(TransferObservation::class, 'transfer_participant_id');
    }

    public function completion(): HasOne
    {
        return $this->hasOne(TransferCompletion::class, 'transfer_participant_id');
    }
}
