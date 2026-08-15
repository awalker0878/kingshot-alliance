<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Domain\Recruitment\Models\RecruitmentQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRecruitmentQuestion
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $options */
    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentQuestion $question,
        string $prompt,
        RecruitmentQuestionType $type,
        bool $isRequired,
        int $position,
        ?string $helpText = null,
        array $options = [],
        bool $isActive = true,
    ): RecruitmentQuestion {
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
            $actor,
            $alliance,
            $question,
            $cleanPrompt,
            $type,
            $isRequired,
            $position,
            $helpText,
            $cleanOptions,
            $isActive,
        ): RecruitmentQuestion {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            $locked = RecruitmentQuestion::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($question->id)
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
                'updated_by_player_id' => $context->actor->id,
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

            return $locked->refresh();
        });
    }
}
