<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $gift_code_source_id
 * @property string $status
 * @property string|null $source_cursor
 * @property string|null $result_cursor
 * @property int $examined_count
 * @property int $accepted_count
 * @property int $duplicate_count
 * @property int $quarantined_count
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 */
final class GiftCodeIngestionRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_source_id',
        'status',
        'source_cursor',
        'result_cursor',
        'examined_count',
        'accepted_count',
        'duplicate_count',
        'quarantined_count',
        'failure_code',
        'failure_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'examined_count' => 'integer',
            'accepted_count' => 'integer',
            'duplicate_count' => 'integer',
            'quarantined_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'gift_code_source_id');
    }
}
