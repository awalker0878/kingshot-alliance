<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Exceptions\ExternalActionBusy;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\ExternalActorActionReceipt;
use App\Contexts\Platform\Integrations\Models\ExternalActorLink;
use App\Contexts\Platform\Integrations\Policies\IntegrationRuntimePolicy;
use App\Contexts\Platform\Integrations\ValueObjects\ExternalActionResult;
use App\Contexts\Platform\Integrations\ValueObjects\ExternalActorLinkReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ExecuteExternalActorAction
{
    public function __construct(
        private IntegrationRuntimePolicy $availability,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  callable(): array<string, mixed>  $execute
     */
    public function handle(
        ExternalActorLinkReference $actor,
        string $apiCredentialId,
        string $idempotencyKey,
        string $action,
        string $requestHash,
        callable $execute,
    ): ExternalActionResult {
        try {
            return DB::transaction(function () use ($actor, $apiCredentialId, $idempotencyKey, $action, $requestHash, $execute): ExternalActionResult {
                // The Workflow already holds current Event scope. A pairing claim may
                // hold credential/link rows while waiting for its Player/Alliance FKs.
                // Acquire those integration rows without waiting to avoid that cycle.
                if (! $this->availability->allowsApi($actor->allianceId)) {
                    throw new AuthorizationException;
                }

                $credential = ApiCredential::query()
                    ->whereKey($apiCredentialId)
                    ->where('alliance_id', $actor->allianceId)
                    ->lock('for update nowait')
                    ->firstOrFail();
                if (! $credential->active() || ! $credential->allows('event-participation:write')) {
                    throw ValidationException::withMessages(['credential' => 'The API credential cannot perform Event participation writes.']);
                }

                $link = ExternalActorLink::query()
                    ->whereKey($actor->linkId)
                    ->where('alliance_id', $actor->allianceId)
                    ->where('player_id', $actor->playerId)
                    ->whereNull('revoked_at')
                    ->lock('for update nowait')
                    ->firstOrFail();
                $receipt = ExternalActorActionReceipt::query()->firstOrCreate(
                    [
                        'external_actor_link_id' => $link->id,
                        'idempotency_key' => $idempotencyKey,
                    ],
                    [
                        'alliance_id' => $actor->allianceId,
                        'api_credential_id' => $credential->id,
                        'action' => $action,
                        'request_hash' => $requestHash,
                        'status' => 'processing',
                    ],
                );
                if (! hash_equals((string) $receipt->request_hash, $requestHash) || $receipt->action !== $action) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This idempotency key was already used for a different request.',
                    ]);
                }
                if (! $receipt->wasRecentlyCreated) {
                    if ($receipt->status !== 'succeeded' || ! is_array($receipt->response)) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This request is already being processed.',
                        ]);
                    }

                    return new ExternalActionResult($receipt->response, true);
                }

                $response = $execute();
                $receipt->forceFill([
                    'status' => 'succeeded',
                    'response' => $response,
                    'completed_at' => now(),
                ])->save();
                $metadata = [
                    'receipt_id' => (string) $receipt->id,
                    'link_id' => (string) $link->id,
                    'api_credential_id' => (string) $credential->id,
                    'action' => $action,
                ];
                $this->audit->record('integration.external_actor.action_succeeded', null, $receipt, $actor->allianceId, $metadata);
                $this->outbox->record(
                    'integration.external_actor.action_succeeded',
                    $actor->allianceId,
                    $receipt,
                    $metadata,
                    'external-action:'.$receipt->id,
                );

                return new ExternalActionResult($response, false);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '55P03') {
                throw new ExternalActionBusy('The external action is busy; retry the same request.', previous: $exception);
            }
            throw $exception;
        }
    }
}
