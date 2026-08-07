<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentCommunication;
use App\Domain\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Domain\Recruitment\Services\RecruitmentOutbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrepareRecruitmentDecisionCommunication
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        RecruitmentDecisionTemplate $template,
    ): RecruitmentCommunication {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to prepare recruitment communications.');
        }

        if ($candidate->alliance_id !== $alliance->id || $template->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate and template must belong to the active alliance.');
        }

        if (! $template->is_active) {
            throw ValidationException::withMessages(['template' => 'This recruitment decision template is inactive.']);
        }

        if ($candidate->stage !== $template->decision_stage) {
            throw ValidationException::withMessages([
                'template' => 'The candidate stage must match the selected decision template.',
            ]);
        }

        $subject = $this->render((string) $template->subject, $candidate, $alliance);
        $body = $this->render((string) $template->body, $candidate, $alliance);
        $idempotencyKey = hash('sha256', implode('|', [
            $alliance->id,
            $candidate->id,
            $template->id,
            $candidate->stage->value,
            $subject,
            $body,
        ]));

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $candidate,
            $template,
            $subject,
            $body,
            $idempotencyKey,
        ): RecruitmentCommunication {
            $existing = RecruitmentCommunication::query()
                ->where('alliance_id', $alliance->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof RecruitmentCommunication) {
                return $existing;
            }

            $communication = RecruitmentCommunication::query()->create([
                'alliance_id' => $alliance->id,
                'candidate_id' => $candidate->id,
                'template_id' => $template->id,
                'channel' => 'email',
                'subject' => $subject,
                'body' => $body,
                'status' => RecruitmentCommunicationStatus::Prepared,
                'idempotency_key' => $idempotencyKey,
                'created_by_user_id' => $actor->id,
            ]);

            $this->audit->record('recruitment.communication.prepared', $actor, $communication, $alliance, [
                'candidate_id' => $candidate->id,
                'template_id' => $template->id,
            ]);
            $this->outbox->record('recruitment.communication.prepared', $alliance, $communication, [
                'candidate_id' => $candidate->id,
                'template_id' => $template->id,
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
