<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\AuditTrail\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $alliance_id
 * @property string|null $actor_user_id
 * @property string|null $actor_player_id
 * @property string $event
 * @property class-string<Model>|string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $metadata
 * @property string|null $request_id
 * @property string|null $trace_id
 * @property Carbon $created_at
 */
final class AuditEvent extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'actor_user_id',
        'actor_player_id',
        'event',
        'subject_type',
        'subject_id',
        'metadata',
        'request_id',
        'trace_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
