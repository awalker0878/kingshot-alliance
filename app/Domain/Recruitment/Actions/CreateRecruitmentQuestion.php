<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Domain\Recruitment\Models\RecruitmentQuestion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRecruitmentQuestion
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
        string $prompt,
        RecruitmentQuestionType $type,
        bool $isRequired,
        int $position = 0,
        ?string $helpText = null,
        array $options = [],
        bool $isActive = true,
    ): RecruitmentQuestion {
        $cleanPrompt = trim($prompt);
        if ($cleanPrompt === '') {
            throw new InvalidArgumentException('Recruitment question prompt is required.');
        }

        if ($position < 0 || $position > 65535) {
            throw new InvalidArgumentException('Recruitment question position is invalid.');
        }

        $cleanOptions = array_values(array_unique(array_filter(array_map(
            static fn (string $option): string => trim($option),
            $options,
        ), static fn (string $option): bool => $option !== '')));

        if (in_array($type, [RecruitmentQuestionType::Select, RecruitmentQuestionType::MultiSelect], true) && $cleanOptions === []) {
            throw new InvalidArgumentException('Select recruitment questions require at least one option.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $cleanPrompt,
            $type,
            $isRequired,
            $position,
            $helpText,
            $cleanOptions,
            $isActive,
        ): RecruitmentQuestion {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            $question = RecruitmentQuestion::query()->create([
                'alliance_id' => $context->alliance->id,
                'prompt' => $cleanPrompt,
                'help_text' => $helpText === null ? null : trim($helpText),
                'question_type' => $type,
                'options' => $cleanOptions === [] ? null : $cleanOptions,
                'is_required' => $isRequired,
                'position' => $position,
                'is_active' => $isActive,
                'created_by_player_id' => $context->actor->id,
                'updated_by_player_id' => $context->actor->id,
            ]);

            $this->audit->record('recruitment.question.created', $context->actor, $question, $context->alliance, [
                'question_type' => $type->value,
                'is_required' => $isRequired,
                'position' => $position,
            ]);
            $this->outbox->record('recruitment.question.created', (string) $context->alliance->id, $question, [
                'question_type' => $type->value,
                'is_required' => $isRequired,
            ]);

            return $question;
        });
    }
}
