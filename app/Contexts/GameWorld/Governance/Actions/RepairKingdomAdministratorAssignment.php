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
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class RepairKingdomAdministratorAssignment
{
    public function __construct(
        private KingdomRoleProvisioner $provisioner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(AuditActor $operator, string $kingdomId, string $targetPlayerId, string $reason, bool $replaceExisting): KingdomAdministratorBootstrap
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'A recovery reason between 10 and 500 characters is required.']);
        }

        return DB::transaction(function () use ($operator, $kingdomId, $targetPlayerId, $reason, $replaceExisting): KingdomAdministratorBootstrap {
            $kingdom = Kingdom::query()->whereKey($kingdomId)->lockForUpdate()->firstOrFail();
            if ($kingdom->status !== KingdomStatus::Active) {
                throw ValidationException::withMessages(['kingdom_id' => 'Recovery requires an active Kingdom.']);
            }
            $target = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();
            if ((string) $target->current_kingdom_id !== $kingdomId || $target->canonical_player_id !== null) {
                throw ValidationException::withMessages(['player_id' => 'The recovery Governor must be a current direct identity in the target Kingdom.']);
            }
            $roles = $this->provisioner->provision($kingdom);
            $administrator = $roles[DefaultKingdomRole::Administrator->value] ?? null;
            $eventCoordinator = $roles[DefaultKingdomRole::EventCoordinator->value] ?? null;
            $viewer = $roles[DefaultKingdomRole::Viewer->value] ?? null;
            if (! $administrator instanceof KingdomRole || ! $eventCoordinator instanceof KingdomRole || ! $viewer instanceof KingdomRole) {
                throw new RuntimeException('The default Kingdom roles were not provisioned.');
            }

            $existing = $replaceExisting ? KingdomRoleAssignment::query()->where('kingdom_id', $kingdomId)
                ->where('kingdom_role_id', $administrator->id)->where('player_id', '!=', $targetPlayerId)
                ->whereNull('revoked_at')->where(static function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->orderBy('id')->limit(501)->lockForUpdate()->get(['id', 'player_id']) : collect();
            if ($existing->count() > 500) {
                throw ValidationException::withMessages(['replace_existing' => 'More than 500 administrator assignments require removal. Recover without replacement, then remove unwanted assignments through ordinary Kingdom role controls.']);
            }
            $assignment = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)->where('player_id', $targetPlayerId)->where('kingdom_role_id', $administrator->id)->lockForUpdate()->first();
            $created = ! $assignment instanceof KingdomRoleAssignment;
            if ($created) {
                $assignment = KingdomRoleAssignment::query()->create([
                    'kingdom_id' => $kingdomId,
                    'player_id' => $targetPlayerId,
                    'kingdom_role_id' => $administrator->id,
                    'reason' => $reason,
                ]);
            }
            $replacedPlayers = $existing->pluck('player_id')->all();
            if ($existing->isNotEmpty()) {
                KingdomRoleAssignment::query()->whereKey($existing->pluck('id'))->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'revocation_reason' => $reason]);
            }

            $metadata = [
                'kingdom_id' => $kingdomId,
                'kingdom_number' => (int) $kingdom->number,
                'target_player_id' => $targetPlayerId,
                'role_key' => DefaultKingdomRole::Administrator->value,
                'reason' => $reason,
                'replace_existing' => $replaceExisting,
                'replaced_player_ids' => $replacedPlayers,
                'recovery_source' => 'platform_admin_recent_auth',
            ];
            if ($created || $existing->isNotEmpty()) {
                $this->audit->record('kingdom.administrator_recovered', $operator, $assignment, null, $metadata);
                $this->outbox->record('kingdom.administrator_recovered', null, $assignment, $metadata);
            }

            return new KingdomAdministratorBootstrap(
                assignmentId: (string) $assignment->id,
                kingdomId: $kingdomId,
                kingdomNumber: (int) $kingdom->number,
                playerId: $targetPlayerId,
                roleKey: DefaultKingdomRole::Administrator->value,
                administratorRoleId: (string) $administrator->id,
                eventCoordinatorRoleId: (string) $eventCoordinator->id,
                viewerRoleId: (string) $viewer->id,
            );
        });
    }
}
