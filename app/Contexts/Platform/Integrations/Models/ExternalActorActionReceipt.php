<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $external_actor_link_id
 * @property string $api_credential_id
 * @property string $idempotency_key
 * @property string $action
 * @property string $request_hash
 * @property string $status
 * @property array<string, mixed>|null $response
 * @property Carbon|null $completed_at
 */
final class ExternalActorActionReceipt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'external_actor_link_id',
        'api_credential_id',
        'idempotency_key',
        'action',
        'request_hash',
        'status',
        'response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
