<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Controllers;

use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Platform\Integrations\Actions\ClaimExternalActorLink;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\ExternalEventParticipation\Actions\ExecuteExternalEventParticipation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ExternalActorApiController extends Controller
{
    public function claim(Request $request, ClaimExternalActorLink $claim): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::enum(ExternalActorProvider::class)],
            'external_subject' => ['required', 'string', 'max:25'],
            'code' => ['required', 'string', 'max:20'],
        ]);
        $link = $claim->handle(
            $this->attribute($request, 'alliance_id'),
            $this->attribute($request, 'api_credential_id'),
            ExternalActorProvider::from((string) $validated['provider']),
            (string) $validated['external_subject'],
            (string) $validated['code'],
        );

        return response()->json([
            'data' => [
                'linked' => true,
                'provider' => $link->provider,
                'subject_hint' => $link->subjectHint,
            ],
        ]);
    }

    public function respond(
        Request $request,
        string $occurrence,
        ExecuteExternalEventParticipation $participation,
    ): JsonResponse {
        $validated = $request->validate([
            'provider' => ['required', Rule::enum(ExternalActorProvider::class)],
            'external_subject' => ['required', 'string', 'max:25'],
            'response' => ['required', Rule::enum(EventResponseChoice::class)],
            'preferred_role' => ['nullable', 'string', 'max:64'],
            'preferred_team' => ['nullable', 'string', 'max:64'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = $participation->respond(
            allianceId: $this->attribute($request, 'alliance_id'),
            apiCredentialId: $this->attribute($request, 'api_credential_id'),
            provider: ExternalActorProvider::from((string) $validated['provider']),
            externalSubject: (string) $validated['external_subject'],
            idempotencyKey: $this->idempotencyKey($request),
            occurrenceId: $occurrence,
            response: EventResponseChoice::from((string) $validated['response']),
            preferredRole: isset($validated['preferred_role']) ? (string) $validated['preferred_role'] : null,
            preferredTeam: isset($validated['preferred_team']) ? (string) $validated['preferred_team'] : null,
            availableFrom: isset($validated['available_from']) ? CarbonImmutable::parse((string) $validated['available_from']) : null,
            availableUntil: isset($validated['available_until']) ? CarbonImmutable::parse((string) $validated['available_until']) : null,
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        );

        return response()->json(['data' => $result->data, 'meta' => ['replayed' => $result->replayed]]);
    }

    public function registration(
        Request $request,
        string $occurrence,
        ExecuteExternalEventParticipation $participation,
    ): JsonResponse {
        $validated = $request->validate([
            'provider' => ['required', Rule::enum(ExternalActorProvider::class)],
            'external_subject' => ['required', 'string', 'max:25'],
            'registered' => ['required', 'boolean'],
        ]);
        $result = $participation->registration(
            allianceId: $this->attribute($request, 'alliance_id'),
            apiCredentialId: $this->attribute($request, 'api_credential_id'),
            provider: ExternalActorProvider::from((string) $validated['provider']),
            externalSubject: (string) $validated['external_subject'],
            idempotencyKey: $this->idempotencyKey($request),
            occurrenceId: $occurrence,
            registered: (bool) $validated['registered'],
        );

        return response()->json(['data' => $result->data, 'meta' => ['replayed' => $result->replayed]]);
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A valid Idempotency-Key header between 8 and 128 characters is required.',
            ]);
        }

        return $key;
    }

    private function attribute(Request $request, string $name): string
    {
        $value = $request->attributes->get($name);
        abort_unless(is_string($value) && $value !== '', 500, 'API authentication context is missing.');

        return $value;
    }
}
