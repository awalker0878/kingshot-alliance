<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentDecisionTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecruitmentDecisionTemplate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
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
                'created_by_player_id' => $actor->id,
                'updated_by_player_id' => $actor->id,
            ]);

            $this->audit->record('recruitment.decision_template.created', $actor, $template, $alliance, [
                'decision_stage' => $decisionStage->value,
            ]);
            $this->outbox->record('recruitment.decision_template.created', (string) $alliance->id, $template, [
                'decision_stage' => $decisionStage->value,
            ]);

            return $template;
        });
    }
}
