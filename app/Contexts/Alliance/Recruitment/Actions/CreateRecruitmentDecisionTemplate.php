<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecruitmentDecisionTemplate
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
        string $name,
        RecruitmentStage $decisionStage,
        string $subject,
        string $body,
        bool $isActive = true,
    ): string {
        if (! in_array($decisionStage, [RecruitmentStage::Accepted, RecruitmentStage::Declined], true)) {
            throw ValidationException::withMessages(['decision_stage' => 'Decision templates must be for accepted or declined candidates.']);
        }

        $cleanName = trim($name);
        $cleanSubject = trim($subject);
        $cleanBody = trim($body);
        if ($cleanName === '' || $cleanSubject === '' || $cleanBody === '') {
            throw ValidationException::withMessages(['template' => 'Template name, subject, and body are required.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $cleanName, $decisionStage, $cleanSubject, $cleanBody, $isActive): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $template = RecruitmentDecisionTemplate::query()->create([
                'alliance_id' => $context->alliance->id,
                'name' => $cleanName,
                'decision_stage' => $decisionStage,
                'subject' => $cleanSubject,
                'body' => $cleanBody,
                'is_active' => $isActive,
                'created_by_player_id' => $context->actor->playerId,
                'updated_by_player_id' => $context->actor->playerId,
            ]);

            $this->audit->record('recruitment.decision_template.created', $context->actor, $template, $context->alliance, [
                'decision_stage' => $decisionStage->value,
            ]);
            $this->outbox->record('recruitment.decision_template.created', (string) $context->alliance->id, $template, [
                'decision_stage' => $decisionStage->value,
            ]);

            return (string) $template->id;
        });
    }
}
