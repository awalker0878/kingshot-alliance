<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
