<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Contexts\GameWorld\Players\Models\Player;
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
        Player $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        RecruitmentDecisionTemplate $template,
    ): RecruitmentCommunication {
        return DB::transaction(function () use ($actor, $alliance, $candidate, $template): RecruitmentCommunication {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $currentCandidate = RecruitmentCandidate::query()
                ->whereKey($candidate->id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($currentCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Prepare communications from the current merged candidate record.',
                ]);
            }

            $currentTemplate = RecruitmentDecisionTemplate::query()
                ->whereKey($template->id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if (! $currentTemplate->is_active) {
                throw ValidationException::withMessages(['template' => 'This recruitment decision template is inactive.']);
            }

            if ($currentCandidate->stage !== $currentTemplate->decision_stage) {
                throw ValidationException::withMessages([
                    'template' => 'The candidate stage must match the selected decision template.',
                ]);
            }

            $subject = $this->render((string) $currentTemplate->subject, $currentCandidate, $context->alliance);
            $body = $this->render((string) $currentTemplate->body, $currentCandidate, $context->alliance);
            $idempotencyKey = hash('sha256', implode('|', [
                $context->alliance->id,
                $currentCandidate->id,
                $currentTemplate->id,
                $currentCandidate->stage->value,
                $subject,
                $body,
            ]));

            $communication = RecruitmentCommunication::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'alliance_id' => $context->alliance->id,
                    'candidate_id' => $currentCandidate->id,
                    'template_id' => $currentTemplate->id,
                    'channel' => 'email',
                    'subject' => $subject,
                    'body' => $body,
                    'status' => RecruitmentCommunicationStatus::Prepared,
                    'created_by_player_id' => $context->actor->id,
                ],
            );

            if (! $communication->wasRecentlyCreated) {
                return $communication;
            }

            $this->audit->record('recruitment.communication.prepared', $context->actor, $communication, $context->alliance, [
                'candidate_id' => $currentCandidate->id,
                'template_id' => $currentTemplate->id,
            ]);
            $this->outbox->record('recruitment.communication.prepared', (string) $context->alliance->id, $communication, [
                'candidate_id' => $currentCandidate->id,
                'template_id' => $currentTemplate->id,
            ]);

            return $communication;
        });
    }

    private function render(string $content, RecruitmentCandidate $candidate, Alliance $alliance): string
    {
        return strtr($content, [
            '{{candidate_name}}' => (string) $candidate->full_name,
            '{{alliance_name}}' => (string) $alliance->name,
        ]);
    }
}
