<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferObservation extends Model
{
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['kind' => TransferObservationKind::class, 'source_type' => TransferSourceType::class, 'numeric_value' => 'integer', 'boolean_value' => 'boolean', 'observed_at' => 'immutable_datetime', 'valid_until' => 'immutable_datetime']; }
    public function participant(): BelongsTo { return $this->belongsTo(TransferParticipant::class, 'transfer_participant_id'); }
    public function targetKingdom(): BelongsTo { return $this->belongsTo(Kingdom::class, 'target_kingdom_id'); }
}
