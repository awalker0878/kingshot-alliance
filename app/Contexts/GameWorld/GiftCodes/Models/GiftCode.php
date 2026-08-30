<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $normalized_code
 * @property string|null $created_by_player_id
 * @property GiftCodeStatus $status
 * @property int $status_revision
 * @property string|null $status_reason_code
 * @property list<string>|null $status_evidence_ids
 * @property CarbonImmutable|null $status_changed_at
 * @property CarbonImmutable|null $status_derived_at
 * @property CarbonImmutable $discovered_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $expires_precision
 * @property int $expires_revision
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, GiftCodeRedemption> $redemptions
 * @property-read Collection<int, GiftCodeProvenance> $provenances
 * @property-read Collection<int, GiftCodeModerationDecision> $moderationDecisions
 * @property-read Collection<int, GiftCodeFactProjection> $factProjections
 * @property-read Collection<int, GiftCodeNotificationCampaign> $notificationCampaigns
 */
final class GiftCode extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'normalized_code',
        'created_by_player_id',
        'status',
        'status_revision',
        'status_reason_code',
        'status_evidence_ids',
        'status_changed_at',
        'status_derived_at',
        'discovered_at',
        'expires_at',
        'expires_precision',
        'expires_revision',
    ];

    protected function casts(): array
    {
        return [
            'status' => GiftCodeStatus::class,
            'status_revision' => 'integer',
            'status_evidence_ids' => 'array',
            'status_changed_at' => 'immutable_datetime',
            'status_derived_at' => 'immutable_datetime',
            'discovered_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'expires_revision' => 'integer',
        ];
    }

    /** @return HasMany<GiftCodeRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCodeRedemption::class);
    }

    /** @return HasMany<GiftCodeProvenance, $this> */
    public function provenances(): HasMany
    {
        return $this->hasMany(GiftCodeProvenance::class)->orderByDesc('observed_at');
    }

    /** @return HasMany<GiftCodeModerationDecision, $this> */
    public function moderationDecisions(): HasMany
    {
        return $this->hasMany(GiftCodeModerationDecision::class)->orderByDesc('decided_at');
    }

    /** @return HasMany<GiftCodeFactProjection, $this> */
    public function factProjections(): HasMany
    {
        return $this->hasMany(GiftCodeFactProjection::class);
    }

    /** @return HasMany<GiftCodeNotificationCampaign, $this> */
    public function notificationCampaigns(): HasMany
    {
        return $this->hasMany(GiftCodeNotificationCampaign::class);
    }
}
