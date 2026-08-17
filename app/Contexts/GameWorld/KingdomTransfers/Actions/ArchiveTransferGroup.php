<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferGroupState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveTransferGroup
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $planId, string $groupId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $planId, $groupId): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);

            $plan = TransferPlan::query()->where('alliance_id', $allianceId)->whereKey($planId)->sharedLock()->firstOrFail();
            $this->assertMutable($context->kingdomId(), $plan);
            $group = TransferGroup::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->whereKey($groupId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($group->state === TransferGroupState::Archived) {
                return;
            }

            $activeParticipants = TransferParticipant::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_plan_id', $plan->id)
                ->where('transfer_group_id', $group->id)
                ->whereNull('withdrawn_at')
                ->orderBy('id')
                ->sharedLock()
                ->get(['id']);

            if ($activeParticipants->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'group' => 'Unassign or move active participants before archiving this transfer group.',
                ]);
            }

            $group->forceFill(['state' => TransferGroupState::Archived])->save();
            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_plan_id' => (string) $plan->id,
                'transfer_group_id' => (string) $group->id,
                'direction' => $group->direction->value,
                'destination_kingdom_id' => $group->destination_kingdom_id,
            ];
            $this->audit->record('kingdoms.transfer_group_archived', $context->actor, $group, null, $metadata);
            $this->outbox->record('kingdoms.transfer_group_archived', $allianceId, $group, $metadata);
        });
    }

    private function assertMutable(string $allianceKingdomId, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages(['group' => 'Transfer groups can only be archived while the transfer cycle is Draft or Open.']);
        }
        if ($allianceKingdomId !== (string) $plan->home_kingdom_id) {
            throw ValidationException::withMessages(['group' => 'The transfer cycle home Kingdom does not match the Alliance Kingdom.']);
        }
    }
}
