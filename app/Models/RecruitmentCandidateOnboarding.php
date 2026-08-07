<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $completed_at */
final class RecruitmentCandidateOnboarding extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'recruitment_candidate_onboarding';
    protected $fillable = [
        'alliance_id',
        'candidate_id',
        'onboarding_item_id',
        'status',
        'completed_at',
        'completed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecruitmentOnboardingStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RecruitmentCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    /** @return BelongsTo<RecruitmentOnboardingItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(RecruitmentOnboardingItem::class, 'onboarding_item_id');
    }
}
