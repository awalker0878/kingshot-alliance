<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;

final class PublicRecruitmentQuery
{
    /** @return array{status: 'closed'|'open'|'invitation_only', application_url: string|null} */
    public function forAlliance(Alliance $alliance): array
    {
        $settings = RecruitmentSetting::query()
            ->where('alliance_id', $alliance->id)
            ->first();

        if (! $settings instanceof RecruitmentSetting || ! $settings->is_open) {
            return [
                'status' => 'closed',
                'application_url' => null,
            ];
        }

        if ($settings->application_mode === RecruitmentApplicationMode::Invitation) {
            return [
                'status' => 'invitation_only',
                'application_url' => null,
            ];
        }

        return [
            'status' => 'open',
            'application_url' => route('public.alliances.recruitment.show', $alliance->slug),
        ];
    }
}
