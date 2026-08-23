<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $label
 * @property CarbonImmutable $pre_transfer_starts_at
 * @property CarbonImmutable $invitational_starts_at
 * @property CarbonImmutable $transfer_opens_at
 * @property CarbonImmutable $ends_at
 * @property TransferSourceType $source_type
 * @property string $source_reference
 * @property CarbonImmutable $observed_at
 * @property string|null $evidence_id
 * @property string|null $recorded_by_player_id
 * @property-read Collection<int, TransferGroup> $groups
 * @property-read Collection<int, TransferKingdomConditionObservation> $conditions
 */
final class TransferWindow extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pre_transfer_starts_at' => 'immutable_datetime',
            'invitational_starts_at' => 'immutable_datetime',
            'transfer_opens_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime',
            'source_type' => TransferSourceType::class,
        ];
    }

    public function phaseAt(DateTimeInterface $now): TransferWindowPhase
    {
        $at = CarbonImmutable::instance($now);

        if ($at->lt($this->pre_transfer_starts_at)) {
            return TransferWindowPhase::NotStarted;
        }
        if ($at->lt($this->invitational_starts_at)) {
            return TransferWindowPhase::PreTransfer;
        }
        if ($at->lt($this->transfer_opens_at)) {
            return TransferWindowPhase::InvitationalTransfer;
        }
        if ($at->lt($this->ends_at)) {
            return TransferWindowPhase::TransferOpens;
        }

        return TransferWindowPhase::Closed;
    }

    /** @return HasMany<TransferGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TransferGroup::class, 'transfer_window_id');
    }

    /** @return HasMany<TransferKingdomConditionObservation, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(TransferKingdomConditionObservation::class, 'transfer_window_id');
    }
}
