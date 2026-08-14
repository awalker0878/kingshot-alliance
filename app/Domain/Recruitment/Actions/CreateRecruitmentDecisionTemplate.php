<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentDecisionTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecruitmentDecisionTemplate
{
    public function __construct(
        private AllianceMutationAuthority $authority,
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
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            $template = RecruitmentDecisionTemplate::query()->create([
                'alliance_id' => $context->alliance->id,
                'name' => $cleanName,
                'decision_stage' => $decisionStage,
                'subject' => $cleanSubject,
                'body' => $cleanBody,
                'is_active' => $isActive,
                'created_by_player_id' => $context->actor->id,
                'updated_by_player_id' => $context->actor->id,
            ]);

            $this->audit->record('recruitment.decision_template.created', $context->actor, $template, $context->alliance, [
                'decision_stage' => $decisionStage->value,
            ]);
            $this->outbox->record('recruitment.decision_template.created', (string) $context->alliance->id, $template, [
                'decision_stage' => $decisionStage->value,
            ]);

            return $template;
        });
    }
}
