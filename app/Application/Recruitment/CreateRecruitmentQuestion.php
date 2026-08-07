<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Models\Alliance;
use App\Models\RecruitmentQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRecruitmentQuestion
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    /** @param list<string> $options */
    public function handle(
        User $actor,
        Alliance $alliance,
        string $prompt,
        RecruitmentQuestionType $type,
        bool $isRequired,
        int $position = 0,
        ?string $helpText = null,
        array $options = [],
        bool $isActive = true,
    ): RecruitmentQuestion {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to manage recruitment questions.');
        }

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
            $question = RecruitmentQuestion::query()->create([
                'alliance_id' => $alliance->id,
                'prompt' => $cleanPrompt,
                'help_text' => $helpText === null ? null : trim($helpText),
                'question_type' => $type,
                'options' => $cleanOptions === [] ? null : $cleanOptions,
                'is_required' => $isRequired,
                'position' => $position,
                'is_active' => $isActive,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->record('recruitment.question.created', $actor, $question, $alliance, [
                'question_type' => $type->value,
                'is_required' => $isRequired,
                'position' => $position,
            ]);
            $this->outbox->record('recruitment.question.created', $alliance, $question, [
                'question_type' => $type->value,
                'is_required' => $isRequired,
            ]);

            return $question;
        });
    }
}
