<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Alliances\Models\Alliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $alliance_id
 * @property string $event_type
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property string $idempotency_key
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 * @property Carbon $available_at
 * @property Carbon|null $published_at
 * @property int $attempts
 * @property string|null $last_error
 */
final class OutboxMessage extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'idempotency_key',
        'payload',
        'occurred_at',
        'available_at',
        'published_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
