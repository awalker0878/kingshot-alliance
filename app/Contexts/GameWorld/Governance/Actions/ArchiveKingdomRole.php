<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveKingdomRole
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $kingdomId, string $roleId): void
    {
        DB::transaction(function () use ($actorPlayerId, $kingdomId, $roleId): void {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            $role = KingdomRole::query()->whereKey($roleId)->where('kingdom_id', $kingdomId)->lockForUpdate()->firstOrFail();
            if ($role->is_system) {
                throw ValidationException::withMessages(['role' => 'System Kingdom roles cannot be archived.']);
            }
            if ($role->archived_at !== null) {
                return;
            }
            if ($role->assignments()->effective()->exists()) {
                throw ValidationException::withMessages(['role' => 'Revoke active assignments before archiving this Kingdom role.']);
            }
            $role->forceFill(['archived_at' => now()])->save();
            $metadata = ['kingdom_id' => $kingdomId, 'role_id' => $roleId, 'role_key' => (string) $role->key];
            $this->audit->record('kingdom.role_archived', $context->actor, $role, null, $metadata);
            $this->outbox->record('kingdom.role_archived', null, $role, $metadata);
        });
    }
}
