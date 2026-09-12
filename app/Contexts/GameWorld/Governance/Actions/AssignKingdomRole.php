<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAdministratorAssignments;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleDelegation;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleInput;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignKingdomRole
{
    public function __construct(
        private KingdomWriteState $kingdomWriteState,
        private KingdomAuthorization $authorization,
        private KingdomRoleDelegation $delegation,
        private KingdomRoleInput $input,
        private KingdomAdministratorAssignments $administrators,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $kingdomId,
        string $targetPlayerId,
        string $roleId,
        ?string $effectiveFrom = null,
        ?string $expiresAt = null,
        ?string $reason = null,
    ): string {
        $reason = $this->input->reason($reason);
        $effective = $this->input->date($effectiveFrom, 'effective_from');
        $expires = $this->input->date($expiresAt, 'expires_at');
        if ($expires !== null && $expires->lte($effective ?? now())) {
            throw ValidationException::withMessages(['expires_at' => 'Role expiry must be after its effective time.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $kingdomId, $targetPlayerId, $roleId, $effective, $expires, $reason): string {
            $authority = $this->kingdomWriteState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($authority, KingdomPermission::RoleManage);

            $target = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();
            if ((string) $target->current_kingdom_id !== $kingdomId || $target->canonical_player_id !== null) {
                throw ValidationException::withMessages(['player_id' => 'The selected Player is not currently in this Kingdom.']);
            }

            $role = KingdomRole::query()
                ->whereKey($roleId)
                ->where('kingdom_id', $kingdomId)
                ->whereNull('archived_at')
                ->lockForUpdate()
                ->firstOrFail();

            $this->delegation->authorizeRole($actorPlayerId, $kingdomId, $role);

            $existing = KingdomRoleAssignment::query()
                ->where('kingdom_id', $kingdomId)
                ->where('player_id', $targetPlayerId)
                ->where('kingdom_role_id', $roleId)
                ->whereNull('revoked_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();
            if ($existing instanceof KingdomRoleAssignment) {
                return (string) $existing->id;
            }

            if ($role->key === DefaultKingdomRole::Administrator->value && $expires !== null) {
                $survivingAdmin = $this->administrators->effective($kingdomId, $expires)
                    ->where('player_id', '!=', $targetPlayerId)
                    ->exists();
                if (! $survivingAdmin) {
                    throw ValidationException::withMessages(['expires_at' => 'A temporary Kingdom Admin requires another administrator whose authority survives that expiry.']);
                }
            }

            $assignment = KingdomRoleAssignment::query()->create([
                'kingdom_id' => $kingdomId,
                'player_id' => $targetPlayerId,
                'kingdom_role_id' => $roleId,
                'assigned_by_player_id' => $actorPlayerId,
                'effective_from' => $effective,
                'expires_at' => $expires,
                'reason' => $reason === null ? null : trim($reason),
            ]);

            $metadata = [
                'kingdom_id' => $kingdomId,
                'kingdom_number' => (int) $authority->kingdom->number,
                'target_player_id' => $targetPlayerId,
                'role_id' => $roleId,
                'role_key' => (string) $role->key,
                'effective_from' => $effective?->toIso8601String(),
                'expires_at' => $expires?->toIso8601String(),
                'reason' => $reason,
            ];
            $event = $expires === null ? 'kingdom.role_assigned' : 'kingdom.role_delegated';
            $this->audit->record($event, $authority->actor, $assignment, null, $metadata);
            $this->outbox->record($event, null, $assignment, $metadata);

            return (string) $assignment->id;
        });
    }
}
