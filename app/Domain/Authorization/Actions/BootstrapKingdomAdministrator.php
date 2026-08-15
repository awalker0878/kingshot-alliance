<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Models\KingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class BootstrapKingdomAdministrator
{
    public function __construct(
        private KingdomRoleProvisioner $provisioner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Kingdom $kingdom, Player $target): KingdomRoleAssignment
    {
        return DB::transaction(function () use ($kingdom, $target): KingdomRoleAssignment {
            $lockedKingdom = Kingdom::query()->whereKey($kingdom->id)->lockForUpdate()->firstOrFail();
            $lockedTarget = Player::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ((string) $lockedTarget->current_kingdom_id !== (string) $lockedKingdom->id) {
                throw ValidationException::withMessages([
                    'player' => 'The bootstrap Player must currently belong to the target Kingdom.',
                ]);
            }

            $administratorExists = KingdomRoleAssignment::query()
                ->where('kingdom_id', $lockedKingdom->id)
                ->whereHas('role', static fn ($query) => $query->where('key', DefaultKingdomRole::Administrator->value))
                ->exists();

            if ($administratorExists) {
                throw ValidationException::withMessages([
                    'kingdom' => 'This Kingdom already has an administrator. Use Player-authorized Kingdom role management.',
                ]);
            }

            $roles = $this->provisioner->provision($lockedKingdom);
            $administrator = $roles[DefaultKingdomRole::Administrator->value] ?? null;
            if (! $administrator instanceof KingdomRole) {
                throw new RuntimeException('The Kingdom Administrator role was not provisioned.');
            }

            $assignment = KingdomRoleAssignment::query()->create([
                'kingdom_id' => $lockedKingdom->id,
                'player_id' => $lockedTarget->id,
                'kingdom_role_id' => $administrator->id,
            ]);

            $metadata = [
                'kingdom_id' => (string) $lockedKingdom->id,
                'kingdom_number' => (int) $lockedKingdom->number,
                'target_player_id' => (string) $lockedTarget->id,
                'role_key' => DefaultKingdomRole::Administrator->value,
                'bootstrap_source' => 'operator_cli',
            ];

            $this->audit->record('kingdom.role_bootstrapped', null, $assignment, null, $metadata);
            $this->outbox->record('kingdom.role_bootstrapped', null, $assignment, $metadata);

            return $assignment->refresh()->load(['kingdom', 'player', 'role']);
        });
    }
}
