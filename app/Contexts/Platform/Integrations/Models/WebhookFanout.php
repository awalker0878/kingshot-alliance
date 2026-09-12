<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed>|null $payload */
final class WebhookFanout extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array',
            'payload_oversized' => 'boolean', 'completed_at' => 'immutable_datetime', 'visited_at' => 'immutable_datetime'];
    }
}
