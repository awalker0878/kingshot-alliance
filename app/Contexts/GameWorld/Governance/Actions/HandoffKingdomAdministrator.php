<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAdministratorAssignments;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleInput;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class HandoffKingdomAdministrator
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private KingdomAdministratorAssignments $administrators,
        private KingdomRoleInput $input,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $kingdomId, string $targetPlayerId, bool $replaceActor = false, ?string $reason = null): string
    {
        $reason = $this->input->reason($reason);

        return DB::transaction(function () use ($actorPlayerId, $kingdomId, $targetPlayerId, $replaceActor, $reason): string {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            $actorAdmin = $this->administrators->effective($kingdomId)->where('player_id', $actorPlayerId)->orderBy('id')->lockForUpdate()->first();
            if (! $actorAdmin instanceof KingdomRoleAssignment) {
                throw ValidationException::withMessages(['actor' => 'Only an effective Kingdom Admin may hand off Kingdom administration.']);
            }
            if ($targetPlayerId === $actorPlayerId) {
                return (string) $actorAdmin->id;
            }
            $target = Player::query()->whereKey($targetPlayerId)->lockForUpdate()->firstOrFail();
            if ((string) $target->current_kingdom_id !== $kingdomId || $target->canonical_player_id !== null) {
                throw ValidationException::withMessages(['player_id' => 'The replacement administrator must currently belong to this Kingdom.']);
            }
            $role = KingdomRole::query()->where('kingdom_id', $kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $replacements = $replaceActor ? KingdomRoleAssignment::query()->where('kingdom_id', $kingdomId)
                ->where('player_id', $actorPlayerId)->where('kingdom_role_id', $role->id)->whereNull('revoked_at')
                ->where(static fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('id')->limit(501)->lockForUpdate()->pluck('id') : collect();
            if ($replacements->count() > 500) {
                throw ValidationException::withMessages(['replace_actor' => 'More than 500 administrator assignments require removal. Add the replacement administrator first, then remove unwanted assignments through ordinary Kingdom role controls.']);
            }
            // Handoff establishes lasting administration even when the target has a temporary grant.
            $targetAssignment = $this->administrators->effective($kingdomId)->where('player_id', $targetPlayerId)
                ->whereNull('expires_at')->orderBy('id')->lockForUpdate()->first();
            $created = ! $targetAssignment instanceof KingdomRoleAssignment;
            if ($created) {
                $targetAssignment = KingdomRoleAssignment::query()->create([
                    'kingdom_id' => $kingdomId,
                    'player_id' => $targetPlayerId,
                    'kingdom_role_id' => $role->id,
                    'assigned_by_player_id' => $actorPlayerId,
                    'reason' => $reason === null ? 'Kingdom administrator handoff' : trim($reason),
                ]);
            }
            if ($replacements->isNotEmpty()) {
                KingdomRoleAssignment::query()->whereKey($replacements)->whereNull('revoked_at')->update([
                    'revoked_at' => now(),
                    'revoked_by_player_id' => $actorPlayerId,
                    'revocation_reason' => $reason === null ? 'Kingdom administrator handoff' : trim($reason),
                ]);
            }
            if (! $this->administrators->effective($kingdomId)->whereNull('expires_at')->exists()) {
                throw ValidationException::withMessages(['role' => 'A Kingdom must retain at least one effective Kingdom Admin.']);
            }
            if ($created || $replacements->isNotEmpty()) {
                $metadata = ['kingdom_id' => $kingdomId, 'from_player_id' => $actorPlayerId, 'target_player_id' => $targetPlayerId, 'replace_actor' => $replaceActor, 'revoked_assignments' => $replacements->count(), 'reason' => $reason];
                $this->audit->record('kingdom.administrator_handoff', $context->actor, $targetAssignment, null, $metadata);
                $this->outbox->record('kingdom.administrator_handoff', null, $targetAssignment, $metadata);
            }

            return (string) $targetAssignment->id;
        });
    }
}
