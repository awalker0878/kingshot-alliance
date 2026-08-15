<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $subscription_id
 * @property string $batch_id
 * @property string $alliance_id
 * @property string $kingdom_id
 * @property KingdomIngestionTargetKind $target_kind
 * @property string|null $stable_game_id
 * @property string|null $source_record_id
 * @property Carbon $captured_at
 * @property array<string, mixed> $normalized_payload
 * @property string $payload_hash
 * @property string $identity_hash
 * @property KingdomIngestionCandidateState $state
 * @property string|null $quarantine_code
 * @property string|null $rejection_code
 * @property string|null $promoted_record_type
 * @property string|null $promoted_record_id
 * @property Carbon|null $promoted_at
 * @property-read KingdomIngestionSubscription $subscription
 * @property-read KingdomIngestionBatch $batch
 * @property-read Alliance $alliance
 * @property-read Kingdom $kingdom
 */
final class KingdomIngestionCandidate extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'subscription_id',
        'batch_id',
        'alliance_id',
        'kingdom_id',
        'target_kind',
        'stable_game_id',
        'source_record_id',
        'captured_at',
        'normalized_payload',
        'payload_hash',
        'identity_hash',
        'state',
        'quarantine_code',
        'rejection_code',
        'promoted_record_type',
        'promoted_record_id',
        'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'target_kind' => KingdomIngestionTargetKind::class,
            'state' => KingdomIngestionCandidateState::class,
            'captured_at' => 'datetime',
            'promoted_at' => 'datetime',
            'normalized_payload' => 'array',
        ];
    }

    /** @return BelongsTo<KingdomIngestionSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(KingdomIngestionSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<KingdomIngestionBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(KingdomIngestionBatch::class, 'batch_id');
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }
}
