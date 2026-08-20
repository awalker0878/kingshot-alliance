<?php

declare(strict_types=1);

namespace App\Workflows\ExternalEventParticipation\Actions;

use App\Contexts\Operations\Participation\Actions\CancelEventRegistration;
use App\Contexts\Operations\Participation\Actions\RegisterForEvent;
use App\Contexts\Operations\Participation\Actions\RespondToEvent;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Enums\EventResponseSource;
use App\Contexts\Platform\Integrations\Actions\ExecuteExternalActorAction;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Queries\ExternalActorLinkQuery;
use App\Contexts\Platform\Integrations\ValueObjects\ExternalActionResult;
use Carbon\CarbonImmutable;

final readonly class ExecuteExternalEventParticipation
{
    public function __construct(
        private ExternalActorLinkQuery $actors,
        private ExecuteExternalActorAction $externalActions,
        private RespondToEvent $respond,
        private RegisterForEvent $register,
        private CancelEventRegistration $cancelRegistration,
    ) {}

    public function respond(
        string $allianceId,
        string $apiCredentialId,
        ExternalActorProvider $provider,
        string $externalSubject,
        string $idempotencyKey,
        string $occurrenceId,
        EventResponseChoice $response,
        ?string $preferredRole = null,
        ?string $preferredTeam = null,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableUntil = null,
        ?string $note = null,
    ): ExternalActionResult {
        $actor = $this->actors->requireActive($allianceId, $provider, $externalSubject);
        $payload = [
            'occurrence_id' => $occurrenceId,
            'response' => $response->value,
            'preferred_role' => $preferredRole,
            'preferred_team' => $preferredTeam,
            'available_from' => $availableFrom?->utc()->toIso8601String(),
            'available_until' => $availableUntil?->utc()->toIso8601String(),
            'note' => $note,
        ];

        return $this->externalActions->handle(
            $actor,
            $apiCredentialId,
            $idempotencyKey,
            'event.response.update',
            $this->requestHash($payload),
            function () use ($actor, $occurrenceId, $response, $preferredRole, $preferredTeam, $availableFrom, $availableUntil, $note): array {
                $this->respond->handle(
                    actorPlayerId: $actor->playerId,
                    occurrenceId: $occurrenceId,
                    response: $response,
                    preferredRole: $preferredRole,
                    preferredTeam: $preferredTeam,
                    availableFrom: $availableFrom,
                    availableUntil: $availableUntil,
                    note: $note,
                    source: EventResponseSource::External,
                );

                return [
                    'occurrence_id' => $occurrenceId,
                    'response' => $response->value,
                ];
            },
        );
    }

    public function registration(
        string $allianceId,
        string $apiCredentialId,
        ExternalActorProvider $provider,
        string $externalSubject,
        string $idempotencyKey,
        string $occurrenceId,
        bool $registered,
    ): ExternalActionResult {
        $actor = $this->actors->requireActive($allianceId, $provider, $externalSubject);
        $payload = ['occurrence_id' => $occurrenceId, 'registered' => $registered];

        return $this->externalActions->handle(
            $actor,
            $apiCredentialId,
            $idempotencyKey,
            'event.registration.update',
            $this->requestHash($payload),
            function () use ($actor, $occurrenceId, $registered): array {
                if ($registered) {
                    $this->register->handle($actor->playerId, $occurrenceId);
                } else {
                    $this->cancelRegistration->handle($actor->playerId, $occurrenceId);
                }

                return [
                    'occurrence_id' => $occurrenceId,
                    'registered' => $registered,
                ];
            },
        );
    }

    /** @param array<string, mixed> $payload */
    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
