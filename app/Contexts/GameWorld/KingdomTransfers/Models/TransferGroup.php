<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class TransferGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_type' => TransferSourceType::class, 'observed_at' => 'immutable_datetime', 'superseded_at' => 'immutable_datetime'];
    }

    public function window(): BelongsTo
    {
        return $this->belongsTo(TransferWindow::class, 'transfer_window_id');
    }

    public function kingdoms(): BelongsToMany
    {
        return $this->belongsToMany(Kingdom::class, 'transfer_group_kingdoms', 'transfer_group_id', 'kingdom_id');
    }
}
