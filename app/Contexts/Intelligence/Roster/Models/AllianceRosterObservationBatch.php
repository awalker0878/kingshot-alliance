<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AllianceRosterObservationBatch extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id', 'source_evidence_id', 'source_review_id', 'schema_version',
        'captured_at', 'destination_idempotency_key', 'accepted_by_player_id', 'accepted_at',
    ];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function observations(): HasMany
    {
        return $this->hasMany(AllianceRosterObservation::class, 'batch_id');
    }
}
