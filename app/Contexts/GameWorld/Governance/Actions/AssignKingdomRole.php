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

final readonly class AssignKingdomRole
{
    public function __construct(
        private KingdomWriteState $kingdomWriteState,
        private KingdomAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $kingdomId,
        string $targetPlayerId,
        DefaultKingdomRole $roleTemplate,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $kingdomId, $targetPlayerId, $roleTemplate): void {
            $authority = $this->kingdomWriteState->lockActiveScope($actorPlayerId, $kingdomId);
            $this->mutations->authorizeContext($authority, KingdomPermission::RoleManage);
            $currentKingdom = $authority->kingdom;
            $currentActor = $authority->actor;

            $lockedTarget = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();
            if ((string) $lockedTarget->current_kingdom_id !== (string) $currentKingdom->id) {
                throw ValidationException::withMessages([
                    'player_id' => 'The selected Player is not currently in this Kingdom.',
                ]);
            }

            $role = KingdomRole::query()
                ->where('kingdom_id', $currentKingdom->id)
                ->where('key', $roleTemplate->value)
                ->firstOrFail();

            $assignment = KingdomRoleAssignment::query()->firstOrCreate([
                'kingdom_id' => $currentKingdom->id,
                'player_id' => $lockedTarget->id,
                'kingdom_role_id' => $role->id,
            ]);

            if (! $assignment->wasRecentlyCreated) {
                return;
            }

            $metadata = [
                'kingdom_id' => (string) $currentKingdom->id,
                'kingdom_number' => (int) $currentKingdom->number,
                'target_player_id' => (string) $lockedTarget->id,
                'role_key' => $roleTemplate->value,
            ];

            $this->audit->record('kingdom.role_assigned', $currentActor, $assignment, null, $metadata);
            $this->outbox->record('kingdom.role_assigned', null, $assignment, $metadata);
        });
    }
}
