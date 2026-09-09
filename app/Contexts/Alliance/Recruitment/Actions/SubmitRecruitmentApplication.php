<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentAnswer;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentApplicationTokenService;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentConfigurationCapacity;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentInput;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SubmitRecruitmentApplication
{
    public function __construct(
        private RecruitmentApplicationTokenService $tokens,
        private AccountIdentityQuery $accounts,
        private KingdomReferenceQuery $kingdoms,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $answers */
    public function handle(
        string $allianceId,
        string $fullName,
        string $email,
        array $answers,
        ?string $contactHandle = null,
        ?string $source = null,
        ?string $applicationToken = null,
        ?int $applicantUserId = null,
    ): string {
        if (count($answers) > RecruitmentConfigurationCapacity::ACTIVE_QUESTIONS) {
            throw ValidationException::withMessages(['answers' => 'Answer only the current application questions.']);
        }
        $cleanName = RecruitmentInput::requiredText($fullName, 'full_name', RecruitmentInput::LIMITS['fullName']);
        $normalizedEmail = RecruitmentInput::email($email);
        $contactHandle = RecruitmentInput::optionalText($contactHandle, 'contact_handle', RecruitmentInput::LIMITS['contactHandle']);
        $source = RecruitmentInput::optionalText($source, 'source', RecruitmentInput::LIMITS['source']);

        return DB::transaction(function () use (
            $allianceId,
            $cleanName,
            $normalizedEmail,
            $answers,
            $contactHandle,
            $source,
            $applicationToken,
            $applicantUserId,
        ): string {
            $currentApplicant = $applicantUserId === null
                ? null
                : $this->accounts->lockActive($applicantUserId);

            // Public submission has no game-domain actor. Use the Alliance only as a
            // lifecycle barrier; Recruitment's singleton settings row is the natural
            // exclusive intake/policy anchor and serializes duplicate-email decisions.
            $currentAlliance = Alliance::query()
                ->whereKey($allianceId)
                ->sharedLock()
                ->firstOrFail();

            if ($currentAlliance->status !== AllianceStatus::Active) {
                throw ValidationException::withMessages([
                    'application' => 'Recruitment applications are unavailable while the Alliance is not active.',
                ]);
            }

            try {
                $this->kingdoms->lockActiveShared((string) $currentAlliance->kingdom_id);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages(['application' => 'Recruitment applications are unavailable while the Kingdom is not active.']);
            }

            $settings = RecruitmentSetting::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->first();

            if (! $settings instanceof RecruitmentSetting
                || ! $settings->is_open
                || $settings->application_mode === RecruitmentApplicationMode::Closed) {
                throw ValidationException::withMessages(['application' => 'Recruitment applications are currently closed.']);
            }

            if ($currentApplicant !== null && Str::lower($currentApplicant->email) !== $normalizedEmail) {
                throw ValidationException::withMessages(['email' => 'Use the email address associated with your account.']);
            }

            $applicationInvite = $this->resolveApplicationInvite(
                (string) $currentAlliance->id,
                $settings->application_mode,
                $normalizedEmail,
                $applicationToken,
            );

            // RecruitmentSetting is held exclusively for the transaction, so all
            // application submissions for this Alliance serialize this non-terminal
            // duplicate-email decision without using the Alliance as a global mutex.
            $duplicate = RecruitmentCandidate::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('email', $normalizedEmail)
                ->whereNull('merged_into_id')
                ->whereNotIn('stage', [
                    RecruitmentStage::Declined->value,
                    RecruitmentStage::Withdrawn->value,
                    RecruitmentStage::Joined->value,
                ])
                ->first();

            if ($duplicate instanceof RecruitmentCandidate) {
                throw ValidationException::withMessages([
                    'email' => 'An active recruitment application already exists for this email address.',
                ]);
            }

            $questions = RecruitmentQuestion::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->sharedLock()
                ->get();

            if (array_diff(array_keys($answers), $questions->modelKeys()) !== []) {
                throw ValidationException::withMessages(['answers' => 'The application questions changed. Refresh the form before submitting.']);
            }

            /** @var list<array{question: RecruitmentQuestion, answer: array<string, mixed>}> $validatedAnswers */
            $validatedAnswers = [];
            $errors = [];

            foreach ($questions as $question) {
                $rawAnswer = $answers[$question->id] ?? null;
                $error = $this->validateAnswer($question, $rawAnswer);

                if ($error !== null) {
                    $errors['answers.'.$question->id] = $error;

                    continue;
                }

                if ($rawAnswer === null || $this->isBlankAnswer($rawAnswer)) {
                    continue;
                }

                $validatedAnswers[] = [
                    'question' => $question,
                    'answer' => $this->normalizeAnswer($question, $rawAnswer),
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $candidate = RecruitmentCandidate::query()->create([
                'alliance_id' => $currentAlliance->id,
                'applicant_user_id' => $currentApplicant?->userId,
                'application_invite_id' => $applicationInvite?->id,
                'full_name' => $cleanName,
                'email' => $normalizedEmail,
                'contact_handle' => $contactHandle,
                'source' => $source,
                'stage' => RecruitmentStage::New,
                'submitted_at' => now(),
            ]);

            foreach ($validatedAnswers as $validated) {
                $question = $validated['question'];
                RecruitmentAnswer::query()->create([
                    'alliance_id' => $currentAlliance->id,
                    'candidate_id' => $candidate->id,
                    'question_id' => $question->id,
                    'prompt_snapshot' => $question->prompt,
                    'question_type_snapshot' => $question->type(),
                    'answer' => $validated['answer'],
                ]);
            }

            RecruitmentStageHistory::query()->create([
                'alliance_id' => $currentAlliance->id,
                'candidate_id' => $candidate->id,
                'from_stage' => null,
                'to_stage' => RecruitmentStage::New,
                'reason' => 'Application submitted',
                'changed_by_player_id' => null,
                'changed_at' => now(),
            ]);

            if ($applicationInvite instanceof RecruitmentApplicationInvite) {
                $applicationInvite->forceFill(['used_at' => now()])->save();
            }

            $this->audit->record('recruitment.application.submitted', $currentApplicant, $candidate, $currentAlliance, [
                'source' => $candidate->source,
                'question_count' => count($validatedAnswers),
                'invitation_based' => $applicationInvite instanceof RecruitmentApplicationInvite,
            ]);
            $this->outbox->record('recruitment.application.submitted', (string) $currentAlliance->id, $candidate, [
                'candidate_id' => $candidate->id,
                'has_source' => $candidate->source !== null && $candidate->source !== '',
            ]);

            return (string) $candidate->id;
        });
    }

    private function resolveApplicationInvite(
        string $allianceId,
        RecruitmentApplicationMode $mode,
        string $normalizedEmail,
        ?string $applicationToken,
    ): ?RecruitmentApplicationInvite {
        if ($mode !== RecruitmentApplicationMode::Invitation) {
            return null;
        }

        if ($applicationToken === null || trim($applicationToken) === '') {
            throw ValidationException::withMessages(['application_token' => 'A recruitment application invitation is required.']);
        }

        $invite = RecruitmentApplicationInvite::query()
            ->where('alliance_id', $allianceId)
            ->where('token_hash', $this->tokens->hash(trim($applicationToken)))
            ->lockForUpdate()
            ->first();

        if (! $invite instanceof RecruitmentApplicationInvite || $invite->used_at !== null || $invite->expires_at->isPast()) {
            throw ValidationException::withMessages(['application_token' => 'This recruitment application invitation is invalid or expired.']);
        }

        if ($invite->email !== null && Str::lower($invite->email) !== $normalizedEmail) {
            throw ValidationException::withMessages(['email' => 'This recruitment application invitation was issued for another email address.']);
        }

        return $invite;
    }

    private function validateAnswer(RecruitmentQuestion $question, mixed $answer): ?string
    {
        if ($question->is_required && ($answer === null || $this->isBlankAnswer($answer))) {
            return 'This question is required.';
        }

        if ($answer === null || $this->isBlankAnswer($answer)) {
            return null;
        }

        $type = $question->type();
        $options = $question->optionValues();

        return match ($type) {
            RecruitmentQuestionType::ShortText, RecruitmentQuestionType::LongText => $this->textAnswerError($answer, $type),
            RecruitmentQuestionType::Select => is_string($answer) && in_array($answer, $options, true)
                ? null
                : 'Choose one of the available options.',
            RecruitmentQuestionType::MultiSelect => is_array($answer)
                && array_is_list($answer)
                && count($answer) <= RecruitmentInput::LIMITS['options']
                && array_reduce($answer, static fn (bool $valid, mixed $item): bool => $valid && is_string($item) && in_array($item, $options, true), true)
                && count(array_unique($answer)) === count($answer)
                ? null
                : 'Choose each available option at most once.',
            RecruitmentQuestionType::Checkbox => is_bool($answer) && (! $question->is_required || $answer)
                ? null
                : 'This checkbox must be confirmed.',
        };
    }

    private function textAnswerError(mixed $answer, RecruitmentQuestionType $type): ?string
    {
        if (! is_string($answer)) {
            return 'This answer must be text.';
        }
        $limit = $type === RecruitmentQuestionType::ShortText ? RecruitmentInput::LIMITS['shortAnswer'] : RecruitmentInput::LIMITS['longAnswer'];

        return mb_strlen(trim($answer)) <= $limit ? null : 'Use no more than '.$limit.' characters.';
    }

    private function isBlankAnswer(mixed $answer): bool
    {
        if (is_string($answer)) {
            return trim($answer) === '';
        }

        if (is_array($answer)) {
            return $answer === [];
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function normalizeAnswer(RecruitmentQuestion $question, mixed $answer): array
    {
        if ($question->type() === RecruitmentQuestionType::MultiSelect) {
            if (! is_array($answer)) {
                throw new LogicException('A validated multi-select recruitment answer must be an array.');
            }

            return ['values' => array_values($answer)];
        }

        return ['value' => is_string($answer) ? trim($answer) : $answer];
    }
}
