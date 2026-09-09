<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Services;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use Illuminate\Validation\ValidationException;

/** Called while the configuration owner holds exclusive Alliance scope. */
final class RecruitmentConfigurationCapacity
{
    public const ACTIVE_QUESTIONS = 30;

    public const ACTIVE_ONBOARDING_ITEMS = 30;

    public function question(string $allianceId, ?string $exceptId = null): void
    {
        $query = RecruitmentQuestion::query()->where('alliance_id', $allianceId)->where('is_active', true);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() >= self::ACTIVE_QUESTIONS) {
            throw ValidationException::withMessages(['active' => 'Use up to '.self::ACTIVE_QUESTIONS.' active questions. Deactivate a question before adding another.']);
        }
    }

    public function onboarding(string $allianceId, ?string $exceptId = null): void
    {
        $query = RecruitmentOnboardingItem::query()->where('alliance_id', $allianceId)->where('is_active', true);
        if ($exceptId !== null) {
            $query->where('id', '<>', $exceptId);
        }
        if ($query->count() >= self::ACTIVE_ONBOARDING_ITEMS) {
            throw ValidationException::withMessages(['active' => 'Use up to '.self::ACTIVE_ONBOARDING_ITEMS.' active onboarding items. Deactivate an item before adding another.']);
        }
    }
}
