<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $transfer_window_id
 * @property string $official_label
 * @property int $revision
 * @property TransferSourceType $source_type
 * @property string $source_reference
 * @property CarbonImmutable $observed_at
 * @property CarbonImmutable|null $superseded_at
 * @property string|null $recorded_by_player_id
 * @property-read TransferWindow $window
 * @property-read Collection<int, Kingdom> $kingdoms
 */
final class TransferGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'source_type' => TransferSourceType::class,
            'observed_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TransferWindow, $this> */
    public function window(): BelongsTo
    {
        return $this->belongsTo(TransferWindow::class, 'transfer_window_id');
    }

    /** @return BelongsToMany<Kingdom, $this> */
    public function kingdoms(): BelongsToMany
    {
        return $this->belongsToMany(Kingdom::class, 'transfer_group_kingdoms', 'transfer_group_id', 'kingdom_id');
    }
}
