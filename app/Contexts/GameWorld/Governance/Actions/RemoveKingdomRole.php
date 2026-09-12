<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAdministratorAssignments;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleInput;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RemoveKingdomRole
{
    public function __construct(
        private KingdomWriteState $kingdomWriteState,
        private KingdomAuthorization $authorization,
        private KingdomRoleInput $input,
        private KingdomAdministratorAssignments $administrators,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $kingdomId, string $assignmentId, ?string $reason = null): void
    {
        $reason = $this->input->reason($reason);
        DB::transaction(function () use ($actorPlayerId, $kingdomId, $assignmentId, $reason): void {
            $authority = $this->kingdomWriteState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($authority, KingdomPermission::RoleManage);

            $assignment = KingdomRoleAssignment::query()
                ->whereKey($assignmentId)
                ->where('kingdom_id', $kingdomId)
                ->with('role')
                ->lockForUpdate()
                ->firstOrFail();
            if ($assignment->revoked_at !== null) {
                return;
            }

            if ($assignment->role->key === DefaultKingdomRole::Administrator->value && $assignment->isEffectiveAt()) {
                $anotherAdminExists = $this->administrators->effective($kingdomId)
                    ->where('id', '!=', $assignment->id)
                    ->when($assignment->expires_at === null, static fn ($query) => $query->whereNull('expires_at'))
                    ->exists();
                if (! $anotherAdminExists) {
                    throw ValidationException::withMessages(['role' => 'A Kingdom must retain effective administration and cannot remove its last lasting Kingdom Admin.']);
                }
            }

            $assignment->forceFill([
                'revoked_at' => now(),
                'revoked_by_player_id' => $actorPlayerId,
                'revocation_reason' => $reason === null ? null : trim($reason),
            ])->save();

            $metadata = [
                'kingdom_id' => $kingdomId,
                'kingdom_number' => (int) $authority->kingdom->number,
                'target_player_id' => (string) $assignment->player_id,
                'role_id' => (string) $assignment->kingdom_role_id,
                'role_key' => (string) $assignment->role->key,
                'reason' => $reason,
            ];
            $this->audit->record('kingdom.role_removed', $authority->actor, $assignment, null, $metadata);
            $this->outbox->record('kingdom.role_removed', null, $assignment, $metadata);
        });
    }
}
