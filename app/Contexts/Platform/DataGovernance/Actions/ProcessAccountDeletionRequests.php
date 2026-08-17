<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Actions;

use App\Contexts\Accounts\Identity\Actions\AnonymizeAccount;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Membership\Actions\RemovePlayersFromAlliances;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayersFromAccount;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use App\Contexts\Platform\DataGovernance\Services\LegalHoldService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ProcessAccountDeletionRequests
{
    public function __construct(
        private LegalHoldService $legalHolds,
        private AccountIdentityQuery $accounts,
        private PlayerReferenceQuery $players,
        private PlayerMembershipQuery $memberships,
        private RemovePlayersFromAlliances $removePlayersFromAlliances,
        private ReleasePlayersFromAccount $releasePlayersFromAccount,
        private AnonymizeAccount $anonymizeAccount,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $processed = 0;
        $requests = AccountDeletionRequest::query()
            ->whereIn('status', ['pending', 'blocked'])
            ->where('eligible_at', '<=', now())
            ->orderBy('eligible_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->get();

        foreach ($requests as $request) {
            if ($this->process((string) $request->id)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function process(string $requestId): bool
    {
        return DB::transaction(function () use ($requestId): bool {
            $request = AccountDeletionRequest::query()->whereKey($requestId)->lockForUpdate()->first();
            if (! $request instanceof AccountDeletionRequest
                || ! in_array($request->status, ['pending', 'blocked'], true)
                || $request->eligible_at->isFuture()) {
                return false;
            }

            $userId = (int) $request->user_id;
            $account = $this->accounts->find($userId);
            if ($account === null || $account->anonymized) {
                $request->forceFill([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'blocked_reason' => null,
                ])->save();

                return true;
            }

            $players = $this->players->ownedByUser($userId);
            $playerIds = array_values(array_map(
                static fn ($player): string => $player->playerId,
                $players,
            ));

            $blockedReason = $this->blockReason($userId, $playerIds);
            if ($blockedReason !== null) {
                $request->forceFill([
                    'status' => 'blocked',
                    'blocked_reason' => $blockedReason,
                ])->save();

                return false;
            }

            $this->removePlayersFromAlliances->handle($players);
            $this->releasePlayersFromAccount->handle($userId, $playerIds);
            $this->anonymizeAccount->handle($userId, $requestId);

            $request->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'blocked_reason' => null,
            ])->save();

            $metadata = [
                'request_id' => $requestId,
                'user_id' => $userId,
                'released_player_ids' => $playerIds,
            ];
            $this->audit->record(
                event: 'platform.account-deletion.processed',
                actor: null,
                subject: $request,
                metadata: $metadata,
            );
            $this->outbox->record(
                eventType: 'platform.account-deletion.processed',
                allianceId: null,
                aggregate: $request,
                payload: $metadata + ['origin' => 'system'],
                idempotencyKey: 'platform.account-deletion.processed:'.$requestId,
                partitionKey: 'account:'.$userId,
            );

            return true;
        });
    }

    /** @param list<string> $playerIds */
    private function blockReason(int $userId, array $playerIds): ?string
    {
        if ($this->legalHolds->active('user', (string) $userId)) {
            return 'An active legal hold prevents account deletion.';
        }

        if (PlatformAdministrator::activeForUserId($userId)) {
            return 'Platform administrator access is still active.';
        }

        if ($this->memberships->hasActiveR5($playerIds)) {
            return 'R5 leadership must be transferred before deleting the account.';
        }

        return null;
    }
}
