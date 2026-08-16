<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class RemoveKingdomRole
{
    public function __construct(
        private KingdomWriteState $kingdomWriteState,
        private KingdomAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Kingdom $kingdom, KingdomRoleAssignment $assignment): void
    {
        if ((string) $assignment->kingdom_id !== (string) $kingdom->id) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $kingdom, $assignment): void {
            $authority = $this->kingdomWriteState->lockActiveScope($actor, $kingdom);
            $this->mutations->authorizeContext($authority, KingdomPermission::RoleManage);
            $currentKingdom = $authority->kingdom;
            $currentActor = $authority->actor;

            $locked = KingdomRoleAssignment::query()
                ->whereKey($assignment->id)
                ->where('kingdom_id', $currentKingdom->id)
                ->with('role:id,key')
                ->lockForUpdate()
                ->firstOrFail();

            Player::query()->whereKey($locked->player_id)->lockForUpdate()->firstOrFail();

            $role = $locked->role;
            if (! $role instanceof KingdomRole) {
                throw new LogicException('A Kingdom role assignment must reference a Kingdom role.');
            }

            if ($role->key === DefaultKingdomRole::Administrator->value) {
                $anotherAdministratorExists = KingdomRoleAssignment::query()
                    ->where('kingdom_id', $currentKingdom->id)
                    ->where('id', '!=', $locked->id)
                    ->whereHas('role', static fn ($query) => $query->where('key', DefaultKingdomRole::Administrator->value))
                    ->exists();

                if (! $anotherAdministratorExists) {
                    throw ValidationException::withMessages([
                        'role' => 'A Kingdom must retain at least one Kingdom Admin.',
                    ]);
                }
            }

            $metadata = [
                'kingdom_id' => (string) $currentKingdom->id,
                'kingdom_number' => (int) $currentKingdom->number,
                'target_player_id' => (string) $locked->player_id,
                'role_key' => $role->key,
            ];

            $this->audit->record('kingdom.role_removed', $currentActor, $locked, null, $metadata);
            $this->outbox->record('kingdom.role_removed', null, $locked, $metadata);
            $locked->delete();
        });
    }
}
