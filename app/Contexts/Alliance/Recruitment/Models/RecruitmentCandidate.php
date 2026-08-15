<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property RecruitmentStage $stage
 * @property Carbon|null $next_action_at
 * @property Carbon $submitted_at
 * @property Carbon|null $first_responded_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $joined_at
 * @property Carbon|null $retention_due_at
 * @property Carbon|null $anonymized_at
 */
final class RecruitmentCandidate extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'applicant_user_id',
        'player_id',
        'application_invite_id',
        'membership_invitation_id',
        'merged_into_id',
        'full_name',
        'email',
        'contact_handle',
        'source',
        'stage',
        'next_action_at',
        'submitted_at',
        'first_responded_at',
        'accepted_at',
        'declined_at',
        'withdrawn_at',
        'joined_at',
        'retention_due_at',
        'anonymized_at',
        'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'stage' => RecruitmentStage::class,
            'next_action_at' => 'datetime',
            'submitted_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'joined_at' => 'datetime',
            'retention_due_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function recruitmentStage(): RecruitmentStage
    {
        $value = $this->getAttribute('stage');

        return $value instanceof RecruitmentStage
            ? $value
            : RecruitmentStage::from((string) $value);
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }


    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /** @return BelongsTo<RecruitmentApplicationInvite, $this> */
    public function applicationInvite(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplicationInvite::class, 'application_invite_id');
    }

    /** @return BelongsTo<RecruitmentCandidate, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** @return HasMany<RecruitmentAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(RecruitmentAnswer::class, 'candidate_id');
    }

    /** @return BelongsToMany<Player, $this> */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(
            Player::class,
            'recruitment_candidate_reviewers',
            'candidate_id',
            'reviewer_player_id',
        )->withPivot(['id', 'alliance_id', 'assigned_by_player_id'])->withTimestamps();
    }

    /** @return HasMany<RecruitmentNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(RecruitmentNote::class, 'candidate_id');
    }

    /** @return BelongsToMany<RecruitmentTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            RecruitmentTag::class,
            'recruitment_candidate_tags',
            'candidate_id',
            'tag_id',
        )->withPivot('alliance_id')->withTimestamps();
    }

    /** @return HasMany<RecruitmentStageHistory, $this> */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(RecruitmentStageHistory::class, 'candidate_id');
    }

    /** @return HasMany<RecruitmentCommunication, $this> */
    public function communications(): HasMany
    {
        return $this->hasMany(RecruitmentCommunication::class, 'candidate_id');
    }

    /** @return HasMany<RecruitmentCandidateOnboarding, $this> */
    public function onboarding(): HasMany
    {
        return $this->hasMany(RecruitmentCandidateOnboarding::class, 'candidate_id');
    }
}
