<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Models\Alliance;
use App\Models\RecruitmentSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ConfigureRecruitmentSettings
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentApplicationMode $mode,
        string $title,
        ?string $introduction,
        int $retentionUnsuccessfulDays,
        bool $isOpen,
    ): RecruitmentSetting {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to manage recruitment.');
        }

        if ($retentionUnsuccessfulDays < 1 || $retentionUnsuccessfulDays > 3650) {
            throw new InvalidArgumentException('Recruitment retention must be between 1 and 3650 days.');
        }

        $cleanTitle = trim($title);
        if ($cleanTitle === '') {
            throw new InvalidArgumentException('Recruitment application title is required.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $mode,
            $cleanTitle,
            $introduction,
            $retentionUnsuccessfulDays,
            $isOpen,
        ): RecruitmentSetting {
            $settings = RecruitmentSetting::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->first();

            $created = ! $settings instanceof RecruitmentSetting;
            if ($created) {
                $settings = new RecruitmentSetting([
                    'alliance_id' => $alliance->id,
                    'created_by_user_id' => $actor->id,
                ]);
            }

            $settings->fill([
                'application_mode' => $mode,
                'title' => $cleanTitle,
                'introduction' => $introduction === null ? null : trim($introduction),
                'retention_unsuccessful_days' => $retentionUnsuccessfulDays,
                'is_open' => $isOpen,
                'updated_by_user_id' => $actor->id,
            ]);
            $settings->save();

            $eventType = $created ? 'recruitment.settings.created' : 'recruitment.settings.updated';
            $this->audit->record($eventType, $actor, $settings, $alliance, [
                'application_mode' => $mode->value,
                'is_open' => $isOpen,
                'retention_unsuccessful_days' => $retentionUnsuccessfulDays,
            ]);
            $this->outbox->record($eventType, $alliance, $settings, [
                'application_mode' => $mode->value,
                'is_open' => $isOpen,
            ]);

            return $settings->refresh();
        });
    }
}
