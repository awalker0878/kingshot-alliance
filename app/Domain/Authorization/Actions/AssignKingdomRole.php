<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomAuthorization;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class AssignKingdomRole
{
    public function __construct(
        private KingdomAuthorization $authorization,
        private KingdomRoleProvisioner $provisioner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Kingdom $kingdom,
        Player $target,
        DefaultKingdomRole $roleTemplate,
    ): KingdomRoleAssignment {
        if (! $this->authorization->allows($actor, $kingdom, PermissionKey::KingdomRoleManage)) {
            throw new AuthorizationException;
        }

        if ((string) $target->current_kingdom_id !== (string) $kingdom->id) {
            throw ValidationException::withMessages([
                'player_id' => 'The selected Player is not currently in this Kingdom.',
            ]);
        }

        return DB::transaction(function () use ($actor, $kingdom, $target, $roleTemplate): KingdomRoleAssignment {
            $lockedKingdom = Kingdom::query()->whereKey($kingdom->id)->lockForUpdate()->firstOrFail();

            if (! $this->authorization->allows($actor, $lockedKingdom, PermissionKey::KingdomRoleManage)) {
                throw new AuthorizationException;
            }

            $lockedTarget = Player::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ((string) $lockedTarget->current_kingdom_id !== (string) $lockedKingdom->id) {
                throw ValidationException::withMessages([
                    'player_id' => 'The selected Player is not currently in this Kingdom.',
                ]);
            }

            $roles = $this->provisioner->provision($lockedKingdom);
            $role = $roles[$roleTemplate->value] ?? null;

            if (! $role instanceof KingdomRole) {
                throw new RuntimeException('The requested Kingdom role was not provisioned.');
            }

            $assignment = KingdomRoleAssignment::query()->firstOrCreate([
                'kingdom_id' => $lockedKingdom->id,
                'player_id' => $lockedTarget->id,
                'kingdom_role_id' => $role->id,
            ]);

            if (! $assignment->wasRecentlyCreated) {
                return $assignment;
            }

            $metadata = [
                'kingdom_id' => (string) $lockedKingdom->id,
                'kingdom_number' => (int) $lockedKingdom->number,
                'target_player_id' => (string) $lockedTarget->id,
                'role_key' => $roleTemplate->value,
            ];

            $this->audit->record('kingdom.role_assigned', $actor, $assignment, null, $metadata);
            $this->outbox->record('kingdom.role_assigned', null, $assignment, $metadata);

            return $assignment;
        });
    }
}
