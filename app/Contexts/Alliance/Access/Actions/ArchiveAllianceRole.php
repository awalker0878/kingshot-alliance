<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveAllianceRole
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $roleId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $roleId): string {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::RoleManage);

            $role = Role::query()
                ->whereKey($roleId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($role->is_system) {
                throw ValidationException::withMessages(['role' => 'System specialist roles cannot be archived.']);
            }
            if ($role->archived_at !== null) {
                return (string) $role->id;
            }

            $assignedMembershipIds = $role->memberships()->pluck('alliance_memberships.id')->map(static fn ($id): string => (string) $id)->all();
            $role->memberships()->detach();
            $role->forceFill(['archived_at' => now()])->save();

            $metadata = [
                'role_id' => (string) $role->id,
                'role_key' => (string) $role->key,
                'removed_membership_ids' => $assignedMembershipIds,
            ];
            $this->audit->record('alliance.role_archived', $context->actor, $role, $context->alliance, $metadata);
            $this->outbox->record('alliance.role_archived', $allianceId, $role, $metadata);

            return (string) $role->id;
        });
    }
}
