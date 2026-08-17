<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAdministratorBootstrap;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
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

    public function handle(string $kingdomId, string $targetPlayerId): KingdomAdministratorBootstrap
    {
        return DB::transaction(function () use ($kingdomId, $targetPlayerId): KingdomAdministratorBootstrap {
            $lockedKingdom = Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail();
            $lockedTarget = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();

            if ((string) $lockedTarget->current_kingdom_id !== (string) $lockedKingdom->id) {
                throw ValidationException::withMessages([
                    'player' => 'The bootstrap Player must currently belong to the target Kingdom.',
                ]);
            }

            $roles = $this->provisioner->provision($lockedKingdom);
            $administrator = $roles[DefaultKingdomRole::Administrator->value] ?? null;
            $eventCoordinator = $roles[DefaultKingdomRole::EventCoordinator->value] ?? null;
            $viewer = $roles[DefaultKingdomRole::Viewer->value] ?? null;

            if (! $administrator instanceof KingdomRole || ! $eventCoordinator instanceof KingdomRole || ! $viewer instanceof KingdomRole) {
                throw new RuntimeException('The default Kingdom roles were not provisioned.');
            }

            $existingAssignment = KingdomRoleAssignment::query()
                ->where('kingdom_id', $lockedKingdom->id)
                ->where('kingdom_role_id', $administrator->id)
                ->lockForUpdate()
                ->first();

            if ($existingAssignment instanceof KingdomRoleAssignment) {
                if ((string) $existingAssignment->player_id !== (string) $lockedTarget->id) {
                    throw ValidationException::withMessages([
                        'kingdom' => 'This Kingdom already has an administrator. Use Player-authorized Kingdom role management.',
                    ]);
                }

                return new KingdomAdministratorBootstrap(
                    assignmentId: (string) $existingAssignment->id,
                    kingdomId: (string) $lockedKingdom->id,
                    kingdomNumber: (int) $lockedKingdom->number,
                    playerId: (string) $lockedTarget->id,
                    roleKey: DefaultKingdomRole::Administrator->value,
                    administratorRoleId: (string) $administrator->id,
                    eventCoordinatorRoleId: (string) $eventCoordinator->id,
                    viewerRoleId: (string) $viewer->id,
                );
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

            return new KingdomAdministratorBootstrap(
                assignmentId: (string) $assignment->id,
                kingdomId: (string) $lockedKingdom->id,
                kingdomNumber: (int) $lockedKingdom->number,
                playerId: (string) $lockedTarget->id,
                roleKey: DefaultKingdomRole::Administrator->value,
                administratorRoleId: (string) $administrator->id,
                eventCoordinatorRoleId: (string) $eventCoordinator->id,
                viewerRoleId: (string) $viewer->id,
            );
        });
    }
}
