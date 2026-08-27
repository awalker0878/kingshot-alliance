<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property EvidenceKind $kind
 * @property array<string,mixed> $payload
 * @property CarbonImmutable $captured_at
 * @property CarbonImmutable $accepted_at
 */
final class GovernorProgressionObservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'payload' => 'array',
            'captured_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
