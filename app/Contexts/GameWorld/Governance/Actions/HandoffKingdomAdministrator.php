<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class HandoffKingdomAdministrator
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $kingdomId, string $targetPlayerId, bool $replaceActor = false, ?string $reason = null): string
    {
        return DB::transaction(function () use ($actorPlayerId, $kingdomId, $targetPlayerId, $replaceActor, $reason): string {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            $actorAdmin = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('player_id', $actorPlayerId)
                ->whereHas('role', static fn ($query) => $query->where('key', DefaultKingdomRole::Administrator->value))->lockForUpdate()->first();
            if (! $actorAdmin instanceof KingdomRoleAssignment) {
                throw ValidationException::withMessages(['actor' => 'Only an effective Kingdom Admin may hand off Kingdom administration.']);
            }
            $target = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();
            if ((string) $target->current_kingdom_id !== $kingdomId) {
                throw ValidationException::withMessages(['player_id' => 'The replacement administrator must currently belong to this Kingdom.']);
            }
            $role = KingdomRole::query()->where('kingdom_id', $kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $targetAssignment = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('player_id', $targetPlayerId)->where('kingdom_role_id', $role->id)->lockForUpdate()->first();
            if (! $targetAssignment instanceof KingdomRoleAssignment) {
                $targetAssignment = KingdomRoleAssignment::query()->create([
                    'kingdom_id' => $kingdomId,
                    'player_id' => $targetPlayerId,
                    'kingdom_role_id' => $role->id,
                    'assigned_by_player_id' => $actorPlayerId,
                    'reason' => $reason === null ? 'Kingdom administrator handoff' : trim($reason),
                ]);
            }
            if ($replaceActor && $targetPlayerId !== $actorPlayerId) {
                KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('player_id', $actorPlayerId)->where('kingdom_role_id', $role->id)
                    ->lockForUpdate()->get()->each(function (KingdomRoleAssignment $assignment) use ($actorPlayerId, $reason): void {
                        $assignment->forceFill([
                            'revoked_at' => now(),
                            'revoked_by_player_id' => $actorPlayerId,
                            'revocation_reason' => $reason === null ? 'Kingdom administrator handoff' : trim($reason),
                        ])->save();
                    });
            }
            if (! KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('kingdom_role_id', $role->id)->exists()) {
                throw ValidationException::withMessages(['role' => 'A Kingdom must retain at least one effective Kingdom Admin.']);
            }
            $metadata = ['kingdom_id' => $kingdomId, 'from_player_id' => $actorPlayerId, 'target_player_id' => $targetPlayerId, 'replace_actor' => $replaceActor, 'reason' => $reason];
            $this->audit->record('kingdom.administrator_handoff', $context->actor, $targetAssignment, null, $metadata);
            $this->outbox->record('kingdom.administrator_handoff', null, $targetAssignment, $metadata);

            return (string) $targetAssignment->id;
        });
    }
}
