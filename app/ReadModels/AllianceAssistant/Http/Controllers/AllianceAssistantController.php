<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\Enums\AssistantStatus;
use App\ReadModels\AllianceAssistant\Queries\AllianceAssistantQuery;
use App\ReadModels\AllianceAssistant\ValueObjects\AssistantResult;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class AllianceAssistantController extends Controller
{
    public function __construct(private readonly PlayerContext $players) {}

    public function index(
        Request $request,
        AllianceContext $context,
        AllianceReferenceQuery $alliances,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);

        return Inertia::render('Assistant/Index', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['name' => $alliance->name],
            'maxQuestionLength' => max(2, (int) config('assistant.max_question_length', 500)),
        ]);
    }

    public function ask(
        Request $request,
        AllianceContext $context,
        AllianceAssistantQuery $assistant,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $actor = $this->players->playerOrNull();
        abort_unless($actor instanceof PlayerReference, 409, 'Select a Player before using Alliance Assistant.');
        $scope = $context->scope();
        abort_unless($scope->playerId === $actor->playerId, 409, 'Alliance context does not match the active Player.');

        $question = trim((string) $request->input('question', ''));
        $validation = $this->validationError($question);
        if ($validation instanceof JsonResponse) {
            return $validation;
        }

        $promptValue = trim((string) $request->input('prompt', ''));
        $prompt = $promptValue === '' ? null : AssistantPrompt::tryFrom($promptValue);
        if ($promptValue !== '' && ! $prompt instanceof AssistantPrompt) {
            return $this->validationResponse();
        }

        $startedAt = hrtime(true);

        try {
            $result = $assistant->ask($actor, $scope, $question, $prompt);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('alliance_assistant.unavailable', [
                'failure_category' => $exception::class,
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return response()->json((new AssistantResult(
                AssistantIntent::Unsupported,
                AssistantStatus::Unavailable,
                'assistant.answers.unavailable',
            ))->toArray(), 503);
        }

        $payload = $result->toArray();
        $sourceTypes = [];
        foreach ($payload['evidence'] as $evidence) {
            if (! is_array($evidence) || ! is_string($evidence['sourceType'] ?? null)) {
                continue;
            }
            $sourceTypes[$evidence['sourceType']] = ($sourceTypes[$evidence['sourceType']] ?? 0) + 1;
        }

        $gameFactResolution = null;
        if ($result->intent === AssistantIntent::GameFact) {
            $candidate = $result->messageParameters['resolution'] ?? null;
            if (is_string($candidate) && in_array($candidate, ['known', 'unknown', 'conflicting'], true)) {
                $gameFactResolution = $candidate;
            }
        }

        Log::info('alliance_assistant.answered', [
            'intent' => $result->intent->value,
            'status' => $result->status->value,
            'evidence_count' => count($result->evidence),
            'source_type_counts' => $sourceTypes,
            'game_fact_resolution' => $gameFactResolution,
            'handoff_kind' => $result->handoff === null ? null : 'navigation',
            'duration_ms' => $this->durationMs($startedAt),
        ]);

        return response()->json($payload);
    }

    private function validationError(string $question): ?JsonResponse
    {
        $length = mb_strlen($question);
        $maximum = max(2, (int) config('assistant.max_question_length', 500));
        $hasControlCharacters = preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $question) === 1;

        if ($length >= 2 && $length <= $maximum && ! $hasControlCharacters) {
            return null;
        }

        return $this->validationResponse();
    }

    private function validationResponse(): JsonResponse
    {
        $result = new AssistantResult(
            AssistantIntent::Unsupported,
            AssistantStatus::ValidationError,
            'assistant.answers.validationError',
            ['max' => max(2, (int) config('assistant.max_question_length', 500))],
        );

        return response()->json($result->toArray(), 422);
    }

    private function durationMs(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }
}
