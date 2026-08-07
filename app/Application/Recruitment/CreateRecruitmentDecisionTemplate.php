<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Models\Alliance;
use App\Models\RecruitmentDecisionTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecruitmentDecisionTemplate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        string $name,
        RecruitmentStage $decisionStage,
        string $subject,
        string $body,
        bool $isActive = true,
    ): RecruitmentDecisionTemplate {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to manage recruitment decision templates.');
        }

        if (! in_array($decisionStage, [RecruitmentStage::Accepted, RecruitmentStage::Declined], true)) {
            throw ValidationException::withMessages(['decision_stage' => 'Decision templates must be for accepted or declined candidates.']);
        }

        $cleanName = trim($name);
        $cleanSubject = trim($subject);
        $cleanBody = trim($body);
        if ($cleanName === '' || $cleanSubject === '' || $cleanBody === '') {
            throw ValidationException::withMessages(['template' => 'Template name, subject, and body are required.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $cleanName, $decisionStage, $cleanSubject, $cleanBody, $isActive): RecruitmentDecisionTemplate {
            $template = RecruitmentDecisionTemplate::query()->create([
                'alliance_id' => $alliance->id,
                'name' => $cleanName,
                'decision_stage' => $decisionStage,
                'subject' => $cleanSubject,
                'body' => $cleanBody,
                'is_active' => $isActive,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->record('recruitment.decision_template.created', $actor, $template, $alliance, [
                'decision_stage' => $decisionStage->value,
            ]);
            $this->outbox->record('recruitment.decision_template.created', $alliance, $template, [
                'decision_stage' => $decisionStage->value,
            ]);

            return $template;
        });
    }
}
