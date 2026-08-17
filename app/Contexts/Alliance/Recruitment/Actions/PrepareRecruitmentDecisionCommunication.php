<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrepareRecruitmentDecisionCommunication
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
        string $candidateId,
        string $templateId,
    ): string {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $candidateId, $templateId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $candidate = RecruitmentCandidate::query()
                ->whereKey($candidateId)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($candidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Prepare communications from the current merged candidate record.',
                ]);
            }

            $template = RecruitmentDecisionTemplate::query()
                ->whereKey($templateId)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if (! $template->is_active) {
                throw ValidationException::withMessages(['template' => 'This recruitment decision template is inactive.']);
            }

            if ($candidate->stage !== $template->decision_stage) {
                throw ValidationException::withMessages([
                    'template' => 'The candidate stage must match the selected decision template.',
                ]);
            }

            $allianceName = (string) $context->alliance->name;
            $subject = $this->render((string) $template->subject, (string) $candidate->full_name, $allianceName);
            $body = $this->render((string) $template->body, (string) $candidate->full_name, $allianceName);
            $idempotencyKey = hash('sha256', implode('|', [
                (string) $context->alliance->id,
                (string) $candidate->id,
                (string) $template->id,
                $candidate->stage->value,
                $subject,
                $body,
            ]));

            $communication = RecruitmentCommunication::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'alliance_id' => $context->alliance->id,
                    'candidate_id' => $candidate->id,
                    'template_id' => $template->id,
                    'channel' => 'email',
                    'subject' => $subject,
                    'body' => $body,
                    'status' => RecruitmentCommunicationStatus::Prepared,
                    'created_by_player_id' => $context->actor->playerId,
                ],
            );

            if ($communication->wasRecentlyCreated) {
                $this->audit->record('recruitment.communication.prepared', $context->actor, $communication, $context->alliance, [
                    'candidate_id' => $candidate->id,
                    'template_id' => $template->id,
                ]);
                $this->outbox->record('recruitment.communication.prepared', (string) $context->alliance->id, $communication, [
                    'candidate_id' => $candidate->id,
                    'template_id' => $template->id,
                ]);
            }

            return (string) $communication->id;
        });
    }

    private function render(string $content, string $candidateName, string $allianceName): string
    {
        return strtr($content, [
            '{{candidate_name}}' => $candidateName,
            '{{alliance_name}}' => $allianceName,
        ]);
    }
}
