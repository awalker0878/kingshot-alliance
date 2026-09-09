<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentInput;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class UpdateRecruitmentQuestion
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<array-key,mixed> $options */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $questionId,
        string $prompt,
        RecruitmentQuestionType $type,
        bool $isRequired,
        int $position,
        ?string $helpText = null,
        array $options = [],
        bool $isActive = true,
    ): string {
        $cleanPrompt = RecruitmentInput::requiredText($prompt, 'prompt', RecruitmentInput::LIMITS['prompt']);
        $helpText = RecruitmentInput::optionalText($helpText, 'help_text', RecruitmentInput::LIMITS['helpText']);
        RecruitmentInput::position($position);
        $cleanOptions = RecruitmentInput::options($options, $type);

        return DB::transaction(function () use (
            $actorPlayerId,
            $allianceId,
            $questionId,
            $cleanPrompt,
            $type,
            $isRequired,
            $position,
            $helpText,
            $cleanOptions,
            $isActive,
        ): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $locked = RecruitmentQuestion::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($questionId)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'prompt' => $cleanPrompt,
                'help_text' => $helpText,
                'question_type' => $type,
                'options' => $cleanOptions === [] ? null : $cleanOptions,
                'is_required' => $isRequired,
                'position' => $position,
                'is_active' => $isActive,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $this->audit->record('recruitment.question.updated', $context->actor, $locked, $context->alliance, [
                'question_type' => $type->value,
                'is_required' => $isRequired,
                'position' => $position,
                'is_active' => $isActive,
            ]);
            $this->outbox->record('recruitment.question.updated', (string) $context->alliance->id, $locked, [
                'question_type' => $type->value,
                'is_required' => $isRequired,
                'position' => $position,
                'is_active' => $isActive,
            ]);

            return (string) $locked->id;
        });
    }
}
