<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Policies\MemberCapacityPolicy;
use App\Contexts\Alliance\Membership\Services\MembershipAdministrationGuard;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateMembershipStatus
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private MembershipAdministrationGuard $guard,
        private MemberCapacityPolicy $entitlements,
        private AuditRecorder $audit,
        private PlayerReferenceQuery $players,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $membershipId, MembershipStatus $status): string
    {
        if (! in_array($status, [MembershipStatus::Active, MembershipStatus::Suspended, MembershipStatus::Removed], true)) {
            throw ValidationException::withMessages(['status' => 'This membership state is not available through administration.']);
        }

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $membershipId, $status): string {
            $context = $status === MembershipStatus::Active
                ? $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId)
                : $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::MembershipManage);

            $membership = AllianceMembership::query()
                ->whereKey($membershipId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->guard->assertCanManage($context, $membership);
            if ($status !== MembershipStatus::Active) {
                $this->guard->assertCanDeactivate($membership);
            }

            $previousStatus = $membership->status;
            if ($status === MembershipStatus::Active) {
                if ($previousStatus !== MembershipStatus::Active) {
                    $this->entitlements->assertCapacity($context->alliance);
                }

                $player = $this->players->require((string) $membership->player_id);
                if ($player->kingdomId !== (string) $context->alliance->kingdom_id) {
                    throw ValidationException::withMessages(['status' => 'The Player must belong to the Alliance Kingdom before this membership can be activated.']);
                }

                $otherActiveMembership = AllianceMembership::query()
                    ->where('player_id', $membership->player_id)
                    ->where('status', MembershipStatus::Active->value)
                    ->where('id', '<>', $membership->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                if ($otherActiveMembership instanceof AllianceMembership) {
                    throw ValidationException::withMessages(['status' => 'The Player already has an active Alliance membership.']);
                }
            }

            if ($previousStatus !== $status) {
                $membership->forceFill([
                    'status' => $status,
                    'rank' => $status === MembershipStatus::Active && $previousStatus === MembershipStatus::Removed ? AllianceRank::R1 : $membership->rank,
                    'joined_at' => $status === MembershipStatus::Active ? ($membership->joined_at ?? now()) : $membership->joined_at,
                    'left_at' => $status === MembershipStatus::Removed ? now() : null,
                ])->save();

                if ($status === MembershipStatus::Removed) {
                    $membership->roles()->detach();
                }

                $metadata = ['previous_status' => $previousStatus->value, 'status' => $status->value, 'player_id' => (string) $membership->player_id];
                $this->audit->record('membership.status_changed', $context->actor, $membership, $context->alliance, $metadata);
                OutboxMessage::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'partition_key' => 'alliance:'.$context->alliance->id,
                    'event_type' => 'membership.status_changed',
                    'aggregate_type' => AllianceMembership::class,
                    'aggregate_id' => $membership->id,
                    'idempotency_key' => 'membership.status_changed:'.$membership->id.':'.$status->value.':'.now()->format('Uu'),
                    'payload' => ['alliance_id' => $context->alliance->id, 'membership_id' => $membership->id, 'player_id' => $membership->player_id, 'previous_status' => $previousStatus->value, 'status' => $status->value],
                    'occurred_at' => now(), 'available_at' => now(), 'attempts' => 0,
                ]);
            }

            return (string) $membership->id;
        });
    }
}
