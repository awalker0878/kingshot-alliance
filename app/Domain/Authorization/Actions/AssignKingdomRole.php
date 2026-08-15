<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomMutationAuthority;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignKingdomRole
{
    public function __construct(
        private KingdomMutationAuthority $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Kingdom $kingdom,
        Player $target,
        DefaultKingdomRole $roleTemplate,
    ): KingdomRoleAssignment {
        return DB::transaction(function () use ($actor, $kingdom, $target, $roleTemplate): KingdomRoleAssignment {
            $authority = $this->mutations->require($actor, $kingdom, PermissionKey::KingdomRoleManage);
            $currentKingdom = $authority->kingdom;
            $currentActor = $authority->actor;

            // A Player row is the Kingdom-authority/state anchor. Lock the target so
            // role changes serialize with Kingdom movement and role-dependent writes.
            $lockedTarget = Player::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
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
                return $assignment;
            }

            $metadata = [
                'kingdom_id' => (string) $currentKingdom->id,
                'kingdom_number' => (int) $currentKingdom->number,
                'target_player_id' => (string) $lockedTarget->id,
                'role_key' => $roleTemplate->value,
            ];

            $this->audit->record('kingdom.role_assigned', $currentActor, $assignment, null, $metadata);
            $this->outbox->record('kingdom.role_assigned', null, $assignment, $metadata);

            return $assignment;
        });
    }
}
