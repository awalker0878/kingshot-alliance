<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property GiftCodeRedemptionSessionMode $mode
 * @property GiftCodeRedemptionSessionStatus $status
 * @property int $total_items
 * @property int $completed_items
 * @property int $skipped_items
 * @property int $failed_items
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $abandoned_at
 */
final class GiftCodeRedemptionSession extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'mode',
        'status',
        'total_items',
        'completed_items',
        'skipped_items',
        'failed_items',
        'last_activity_at',
        'completed_at',
        'abandoned_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => GiftCodeRedemptionSessionMode::class,
            'status' => GiftCodeRedemptionSessionStatus::class,
            'total_items' => 'integer',
            'completed_items' => 'integer',
            'skipped_items' => 'integer',
            'failed_items' => 'integer',
            'last_activity_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'abandoned_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<GiftCodeRedemptionSessionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GiftCodeRedemptionSessionItem::class, 'session_id')->orderBy('sequence');
    }
}
