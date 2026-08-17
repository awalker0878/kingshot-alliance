<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Services\MembershipAdministrationGuard;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class EndMembershipForTransfer
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private MembershipAdministrationGuard $guard,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $targetPlayerId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $targetPlayerId): void {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::MembershipManage);
            $membership = AllianceMembership::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $targetPlayerId)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->first();
            if (! $membership instanceof AllianceMembership) {
                return;
            }

            if ((string) $membership->player_id !== $actorPlayerId) {
                $this->guard->assertCanManage($context, $membership);
            }
            $this->guard->assertCanDeactivate($membership);
            $membership->forceFill(['status' => MembershipStatus::Left, 'left_at' => now()])->save();
            $membership->roles()->detach();

            $metadata = ['membership_id' => (string) $membership->id, 'player_id' => $targetPlayerId, 'reason' => 'kingdom_transfer'];
            $this->audit->record('membership.transfer_handoff_completed', $context->actor, $membership, $context->alliance, $metadata);
            $this->outbox->record('membership.transfer_handoff_completed', $allianceId, $membership, $metadata);
        });
    }
}
