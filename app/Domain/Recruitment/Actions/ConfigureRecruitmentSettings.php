<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Models\RecruitmentSetting;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ConfigureRecruitmentSettings
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentApplicationMode $mode,
        string $title,
        ?string $introduction,
        int $retentionUnsuccessfulDays,
        bool $isOpen,
    ): RecruitmentSetting {
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
            // Recruitment settings are one singleton row per Alliance. Exclusive
            // parent coordination prevents concurrent first-create races.
            $context = $this->authority->requireExclusive(
                $actor,
                $alliance,
                PermissionKey::RecruitmentManage,
            );

            $settings = RecruitmentSetting::query()
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->first();

            $created = ! $settings instanceof RecruitmentSetting;
            if ($created) {
                $settings = new RecruitmentSetting([
                    'alliance_id' => $context->alliance->id,
                    'created_by_player_id' => $context->actor->id,
                ]);
            }

            $settings->fill([
                'application_mode' => $mode,
                'title' => $cleanTitle,
                'introduction' => $introduction === null ? null : trim($introduction),
                'retention_unsuccessful_days' => $retentionUnsuccessfulDays,
                'is_open' => $isOpen,
                'updated_by_player_id' => $context->actor->id,
            ]);
            $settings->save();

            $eventType = $created ? 'recruitment.settings.created' : 'recruitment.settings.updated';
            $this->audit->record($eventType, $context->actor, $settings, $context->alliance, [
                'application_mode' => $mode->value,
                'is_open' => $isOpen,
                'retention_unsuccessful_days' => $retentionUnsuccessfulDays,
            ]);
            $this->outbox->record($eventType, (string) $context->alliance->id, $settings, [
                'application_mode' => $mode->value,
                'is_open' => $isOpen,
            ]);

            return $settings->refresh();
        });
    }
}
