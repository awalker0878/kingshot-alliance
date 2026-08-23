<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferKingdomConditionObservation extends Model
{
    use HasUlids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected function casts(): array { return ['power_cap' => 'integer', 'classification' => TransferKingdomClassification::class, 'source_type' => TransferSourceType::class, 'observed_at' => 'immutable_datetime', 'is_correction' => 'boolean']; }
    public function window(): BelongsTo { return $this->belongsTo(TransferWindow::class, 'transfer_window_id'); }
    public function kingdom(): BelongsTo { return $this->belongsTo(Kingdom::class, 'kingdom_id'); }
}
