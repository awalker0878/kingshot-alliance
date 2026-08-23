<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class TransferWindow extends Model
{
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pre_transfer_starts_at' => 'immutable_datetime', 'invitational_starts_at' => 'immutable_datetime',
            'transfer_opens_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
            'observed_at' => 'immutable_datetime', 'source_type' => TransferSourceType::class,
        ];
    }

    public function phaseAt(Carbon $now): TransferWindowPhase
    {
        if ($now->lt($this->pre_transfer_starts_at)) return TransferWindowPhase::NotStarted;
        if ($now->lt($this->invitational_starts_at)) return TransferWindowPhase::PreTransfer;
        if ($now->lt($this->transfer_opens_at)) return TransferWindowPhase::InvitationalTransfer;
        if ($now->lt($this->ends_at)) return TransferWindowPhase::TransferOpens;
        return TransferWindowPhase::Closed;
    }

    public function groups(): HasMany { return $this->hasMany(TransferGroup::class, 'transfer_window_id'); }
    public function conditions(): HasMany { return $this->hasMany(TransferKingdomConditionObservation::class, 'transfer_window_id'); }
}
