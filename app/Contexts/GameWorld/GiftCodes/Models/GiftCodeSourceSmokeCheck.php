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
 * @property string|null $adapter_key
 * @property string $status
 * @property array<string,mixed>|null $readiness
 * @property int $observation_count
 * @property string|null $retrieval_version
 * @property string|null $provider_request_id
 * @property int $duration_ms
 * @property string|null $push_status
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property int|null $checked_by_user_id
 * @property CarbonImmutable $checked_at
 */
final class GiftCodeSourceSmokeCheck extends Model
{
    use HasUlids;

    protected $table = 'gift_code_source_smoke_checks';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_source_id',
        'adapter_key',
        'status',
        'readiness',
        'observation_count',
        'retrieval_version',
        'provider_request_id',
        'duration_ms',
        'push_status',
        'failure_code',
        'failure_message',
        'checked_by_user_id',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'readiness' => 'array',
            'observation_count' => 'integer',
            'duration_ms' => 'integer',
            'checked_by_user_id' => 'integer',
            'checked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'gift_code_source_id');
    }
}
