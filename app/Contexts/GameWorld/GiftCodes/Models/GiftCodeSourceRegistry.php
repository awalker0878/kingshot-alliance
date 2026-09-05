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
 * @property array<string, mixed>|null $provenance_policy
 * @property bool $ingestion_enabled
 * @property int $policy_revision
 * @property string $activation_status
 * @property string $health_status
 * @property string|null $ingestion_cursor
 * @property array<string,mixed>|null $ingestion_checkpoint
 * @property CarbonImmutable|null $next_eligible_ingestion_at
 * @property int $consecutive_failures
 * @property int $request_count
 * @property int $observation_count
 * @property int $duplicate_observation_count
 * @property int $rate_limit_event_count
 * @property CarbonImmutable|null $last_observation_at
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
        'activation_status',
        'health_status',
        'ingestion_cursor',
        'ingestion_checkpoint',
        'next_eligible_ingestion_at',
        'consecutive_failures',
        'request_count',
        'observation_count',
        'duplicate_observation_count',
        'rate_limit_event_count',
        'last_observation_at',
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
            'policy_revision' => 'integer',
            'ingestion_checkpoint' => 'array',
            'next_eligible_ingestion_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
            'request_count' => 'integer',
            'observation_count' => 'integer',
            'duplicate_observation_count' => 'integer',
            'rate_limit_event_count' => 'integer',
            'last_observation_at' => 'immutable_datetime',
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
}
