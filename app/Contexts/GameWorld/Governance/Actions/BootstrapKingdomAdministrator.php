<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAdministratorBootstrap;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
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
            $kingdom = Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail();
            if ($kingdom->status !== KingdomStatus::Active) {
                throw ValidationException::withMessages(['kingdom' => 'An archived Kingdom cannot bootstrap an administrator.']);
            }

            $target = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();
            if ((string) $target->current_kingdom_id !== (string) $kingdom->id) {
                throw ValidationException::withMessages(['player' => 'The bootstrap Player must currently belong to the target Kingdom.']);
            }

            $roles = $this->provisioner->provision($kingdom);
            $administrator = $roles[DefaultKingdomRole::Administrator->value] ?? null;
            $eventCoordinator = $roles[DefaultKingdomRole::EventCoordinator->value] ?? null;
            $viewer = $roles[DefaultKingdomRole::Viewer->value] ?? null;
            if (! $administrator instanceof KingdomRole || ! $eventCoordinator instanceof KingdomRole || ! $viewer instanceof KingdomRole) {
                throw new RuntimeException('The default Kingdom roles were not provisioned.');
            }

            $historicalAssignment = KingdomRoleAssignment::query()
                ->where('kingdom_id', $kingdom->id)
                ->where('kingdom_role_id', $administrator->id)
                ->with('role')
                ->lockForUpdate()
                ->first();
            if ($historicalAssignment instanceof KingdomRoleAssignment) {
                if ((string) $historicalAssignment->player_id === (string) $target->id && $historicalAssignment->isEffectiveAt()) {
                    return $this->result($historicalAssignment, $kingdom, $target, $administrator, $eventCoordinator, $viewer);
                }
                throw ValidationException::withMessages(['kingdom' => 'This Kingdom has already had an administrator. Use Player-authorized handoff or Platform break-glass recovery.']);
            }

            $assignment = KingdomRoleAssignment::query()->create([
                'kingdom_id' => $kingdom->id,
                'player_id' => $target->id,
                'kingdom_role_id' => $administrator->id,
                'reason' => 'Initial Kingdom administrator bootstrap',
            ]);
            $metadata = ['kingdom_id' => (string) $kingdom->id, 'kingdom_number' => (int) $kingdom->number, 'target_player_id' => (string) $target->id, 'role_key' => DefaultKingdomRole::Administrator->value, 'bootstrap_source' => 'operator_cli'];
            $this->audit->record('kingdom.role_bootstrapped', null, $assignment, null, $metadata);
            $this->outbox->record('kingdom.role_bootstrapped', null, $assignment, $metadata);

            return $this->result($assignment, $kingdom, $target, $administrator, $eventCoordinator, $viewer);
        });
    }

    private function result(KingdomRoleAssignment $assignment, Kingdom $kingdom, Player $target, KingdomRole $administrator, KingdomRole $eventCoordinator, KingdomRole $viewer): KingdomAdministratorBootstrap
    {
        return new KingdomAdministratorBootstrap(
            assignmentId: (string) $assignment->id,
            kingdomId: (string) $kingdom->id,
            kingdomNumber: (int) $kingdom->number,
            playerId: (string) $target->id,
            roleKey: DefaultKingdomRole::Administrator->value,
            administratorRoleId: (string) $administrator->id,
            eventCoordinatorRoleId: (string) $eventCoordinator->id,
            viewerRoleId: (string) $viewer->id,
        );
    }
}
