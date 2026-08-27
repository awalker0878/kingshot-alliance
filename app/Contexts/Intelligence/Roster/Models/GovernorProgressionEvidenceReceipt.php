<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property EvidenceKind $evidence_kind
 * @property CarbonImmutable $accepted_at
 */
final class GovernorProgressionEvidenceReceipt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_kind' => EvidenceKind::class,
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
