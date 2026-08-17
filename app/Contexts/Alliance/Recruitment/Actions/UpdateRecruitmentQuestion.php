<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRecruitmentQuestion
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $options */
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
        $cleanPrompt = trim($prompt);
        if ($cleanPrompt === '') {
            throw ValidationException::withMessages(['prompt' => 'Recruitment question prompt is required.']);
        }

        if ($position < 0 || $position > 65535) {
            throw ValidationException::withMessages(['position' => 'Recruitment question position is invalid.']);
        }

        $cleanOptions = array_values(array_unique(array_filter(array_map(
            static fn (string $option): string => trim($option),
            $options,
        ), static fn (string $option): bool => $option !== '')));

        if (in_array($type, [RecruitmentQuestionType::Select, RecruitmentQuestionType::MultiSelect], true) && $cleanOptions === []) {
            throw ValidationException::withMessages(['options' => 'Select recruitment questions require at least one option.']);
        }

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
                'help_text' => $helpText === null ? null : trim($helpText),
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
