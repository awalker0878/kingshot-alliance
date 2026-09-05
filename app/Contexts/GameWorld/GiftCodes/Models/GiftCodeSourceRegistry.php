<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $source_key
 * @property string $name
 * @property string $classification
 * @property string|null $canonical_domain
 * @property bool $is_active
 * @property string $verification_method
 * @property string|null $adapter_key
 * @property array<string,mixed>|null $provenance_policy
 * @property bool $ingestion_enabled
 * @property bool $push_enabled
 * @property bool $head_poll_enabled
 * @property bool $reconciliation_enabled
 * @property bool $backfill_enabled
 * @property bool $authority_promotion_enabled
 * @property int $policy_revision
 * @property string $activation_status
 * @property string $health_status
 * @property CarbonImmutable|null $next_eligible_ingestion_at
 * @property int $consecutive_failures
 * @property int $consecutive_quarantined_runs
 * @property int $request_count
 * @property int $observation_count
 * @property int $accepted_observation_count
 * @property int $quarantined_observation_count
 * @property int $duplicate_observation_count
 * @property int $rate_limit_event_count
 * @property int $reconciliation_gap_count
 * @property int $signature_failure_count
 * @property int $replay_rejection_count
 * @property CarbonImmutable|null $last_observation_at
 * @property CarbonImmutable|null $last_accepted_observation_at
 * @property CarbonImmutable|null $last_quarantined_observation_at
 * @property CarbonImmutable|null $last_push_received_at
 * @property CarbonImmutable|null $last_provider_event_at
 * @property CarbonImmutable|null $last_reconciliation_gap_at
 * @property CarbonImmutable|null $last_health_checked_at
 * @property string|null $last_provider_request_id
 * @property string|null $last_retrieval_version
 * @property int|null $last_quota_remaining
 * @property int|null $last_rate_limit_remaining
 * @property int|null $last_retry_after_seconds
 * @property CarbonImmutable|null $last_ingestion_attempt_at
 * @property CarbonImmutable|null $last_ingestion_success_at
 * @property CarbonImmutable|null $last_ingestion_failure_at
 * @property string|null $last_ingestion_failure_code
 * @property string|null $last_ingestion_error
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class GiftCodeSourceRegistry extends Model
{
    use HasUlids;

    protected $table = 'gift_code_sources';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'source_key',
        'name',
        'classification',
        'canonical_domain',
        'is_active',
        'verification_method',
        'adapter_key',
        'policy_revision',
        'provenance_policy',
        'ingestion_enabled',
        'push_enabled',
        'head_poll_enabled',
        'reconciliation_enabled',
        'backfill_enabled',
        'authority_promotion_enabled',
        'activation_status',
        'health_status',
        'next_eligible_ingestion_at',
        'consecutive_failures',
        'consecutive_quarantined_runs',
        'request_count',
        'observation_count',
        'accepted_observation_count',
        'quarantined_observation_count',
        'duplicate_observation_count',
        'rate_limit_event_count',
        'reconciliation_gap_count',
        'signature_failure_count',
        'replay_rejection_count',
        'last_observation_at',
        'last_accepted_observation_at',
        'last_quarantined_observation_at',
        'last_push_received_at',
        'last_provider_event_at',
        'last_reconciliation_gap_at',
        'last_health_checked_at',
        'last_provider_request_id',
        'last_retrieval_version',
        'last_quota_remaining',
        'last_rate_limit_remaining',
        'last_retry_after_seconds',
        'last_ingestion_attempt_at',
        'last_ingestion_success_at',
        'last_ingestion_failure_at',
        'last_ingestion_failure_code',
        'last_ingestion_error',
        'revoked_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'provenance_policy' => 'array',
            'ingestion_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'head_poll_enabled' => 'boolean',
            'reconciliation_enabled' => 'boolean',
            'backfill_enabled' => 'boolean',
            'authority_promotion_enabled' => 'boolean',
            'policy_revision' => 'integer',
            'next_eligible_ingestion_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
            'consecutive_quarantined_runs' => 'integer',
            'request_count' => 'integer',
            'observation_count' => 'integer',
            'accepted_observation_count' => 'integer',
            'quarantined_observation_count' => 'integer',
            'duplicate_observation_count' => 'integer',
            'rate_limit_event_count' => 'integer',
            'reconciliation_gap_count' => 'integer',
            'signature_failure_count' => 'integer',
            'replay_rejection_count' => 'integer',
            'last_observation_at' => 'immutable_datetime',
            'last_accepted_observation_at' => 'immutable_datetime',
            'last_quarantined_observation_at' => 'immutable_datetime',
            'last_push_received_at' => 'immutable_datetime',
            'last_provider_event_at' => 'immutable_datetime',
            'last_reconciliation_gap_at' => 'immutable_datetime',
            'last_health_checked_at' => 'immutable_datetime',
            'last_quota_remaining' => 'integer',
            'last_rate_limit_remaining' => 'integer',
            'last_retry_after_seconds' => 'integer',
            'last_ingestion_attempt_at' => 'immutable_datetime',
            'last_ingestion_success_at' => 'immutable_datetime',
            'last_ingestion_failure_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<GiftCodeProvenance, $this> */
    public function provenances(): HasMany
    {
        return $this->hasMany(GiftCodeProvenance::class, 'registered_source_id');
    }

    /** @return HasMany<GiftCodeIngestionRun, $this> */
    public function ingestionRuns(): HasMany
    {
        return $this->hasMany(GiftCodeIngestionRun::class, 'gift_code_source_id');
    }

    /** @return HasMany<GiftCodeSourceSyncState, $this> */
    public function syncStates(): HasMany
    {
        return $this->hasMany(GiftCodeSourceSyncState::class, 'gift_code_source_id');
    }

    /** @return HasMany<GiftCodeSourceSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(GiftCodeSourceSubscription::class, 'gift_code_source_id');
    }

    /** @return HasMany<GiftCodeSourceDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(GiftCodeSourceDelivery::class, 'gift_code_source_id');
    }
}
