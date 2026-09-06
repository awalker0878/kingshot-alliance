<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ReconcileKingdomSystemPolicy
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private KingdomRoleProvisioner $provisioner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @return array{administrator:string,eventCoordinator:string,viewer:string} */
    public function handle(string $actorPlayerId, string $kingdomId): array
    {
        return DB::transaction(function () use ($actorPlayerId, $kingdomId): array {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            $roles = $this->provisioner->provision($context->kingdom);
            foreach ([DefaultKingdomRole::Administrator, DefaultKingdomRole::EventCoordinator, DefaultKingdomRole::Viewer] as $template) {
                if (! ($roles[$template->value] ?? null) instanceof KingdomRole) {
                    throw new RuntimeException('Default Kingdom governance policy could not be provisioned.');
                }
            }
            $metadata = ['kingdom_id' => $kingdomId, 'policy' => 'default-v1'];
            $this->audit->record('kingdom.system_policy_reconciled', $context->actor, $context->kingdom, null, $metadata);
            $this->outbox->record('kingdom.system_policy_reconciled', null, $context->kingdom, $metadata);

            return [
                'administrator' => (string) $roles[DefaultKingdomRole::Administrator->value]->id,
                'eventCoordinator' => (string) $roles[DefaultKingdomRole::EventCoordinator->value]->id,
                'viewer' => (string) $roles[DefaultKingdomRole::Viewer->value]->id,
            ];
        });
    }
}
