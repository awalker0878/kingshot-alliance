<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomMutationAuthority;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class RemoveKingdomRole
{
    public function __construct(
        private KingdomMutationAuthority $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Kingdom $kingdom, KingdomRoleAssignment $assignment): void
    {
        if ((string) $assignment->kingdom_id !== (string) $kingdom->id) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $kingdom, $assignment): void {
            $authority = $this->mutations->require($actor, $kingdom, PermissionKey::KingdomRoleManage);
            $currentKingdom = $authority->kingdom;
            $currentActor = $authority->actor;

            $locked = KingdomRoleAssignment::query()
                ->whereKey($assignment->id)
                ->where('kingdom_id', $currentKingdom->id)
                ->with('role:id,key')
                ->lockForUpdate()
                ->firstOrFail();

            // Lock the target Player authority anchor so removal serializes with
            // Kingdom movement and mutations authorized by this Player's roles.
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
