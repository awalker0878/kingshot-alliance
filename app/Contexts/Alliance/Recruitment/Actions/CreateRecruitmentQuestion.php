<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateRecruitmentQuestion
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
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
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

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
