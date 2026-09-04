<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $source_evidence_id
 * @property string $source_review_id
 * @property string $schema_version
 * @property Carbon $captured_at
 * @property string $destination_idempotency_key
 * @property string $accepted_by_player_id
 * @property Carbon $accepted_at
 * @property int|null $observations_count
 * @property-read Collection<int, AllianceRosterObservation> $observations
 */
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

    /** @return HasMany<AllianceRosterObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(AllianceRosterObservation::class, 'batch_id');
    }
}
