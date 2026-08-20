<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ConfigureRecruitmentSettings
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        RecruitmentApplicationMode $mode,
        string $title,
        ?string $introduction,
        int $retentionUnsuccessfulDays,
        bool $isOpen,
        bool $isListed,
    ): string {
        if ($retentionUnsuccessfulDays < 1 || $retentionUnsuccessfulDays > 3650) {
            throw new InvalidArgumentException('Recruitment retention must be between 1 and 3650 days.');
        }

        $cleanTitle = trim($title);
        if ($cleanTitle === '') {
            throw new InvalidArgumentException('Recruitment application title is required.');
        }

        return DB::transaction(function () use (
            $actorPlayerId,
            $allianceId,
            $mode,
            $cleanTitle,
            $introduction,
            $retentionUnsuccessfulDays,
            $isOpen,
            $isListed,
        ): string {
            // Recruitment settings are one singleton row per Alliance. Exclusive
            // parent coordination prevents concurrent first-create races.
            $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $settings = RecruitmentSetting::query()
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->first();

            $created = ! $settings instanceof RecruitmentSetting;
            if ($created) {
                $settings = new RecruitmentSetting([
                    'alliance_id' => $context->alliance->id,
                    'created_by_player_id' => $context->actor->playerId,
                ]);
            }

            $settings->fill([
                'application_mode' => $mode,
                'title' => $cleanTitle,
                'introduction' => $introduction === null ? null : trim($introduction),
                'retention_unsuccessful_days' => $retentionUnsuccessfulDays,
                'is_open' => $isOpen,
                'is_listed' => $isListed,
                'updated_by_player_id' => $context->actor->playerId,
            ]);
            $settings->save();

            $eventType = $created ? 'recruitment.settings.created' : 'recruitment.settings.updated';
            $this->audit->record($eventType, $context->actor, $settings, $context->alliance, [
                'application_mode' => $mode->value,
                'is_open' => $isOpen,
                'is_listed' => $isListed,
                'retention_unsuccessful_days' => $retentionUnsuccessfulDays,
            ]);
            $this->outbox->record($eventType, (string) $context->alliance->id, $settings, [
                'application_mode' => $mode->value,
                'is_open' => $isOpen,
                'is_listed' => $isListed,
            ]);

            return (string) $settings->id;
        });
    }
}
