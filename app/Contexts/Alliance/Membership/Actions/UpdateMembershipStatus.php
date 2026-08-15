<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Services\MembershipAdministrationGuard;
use App\Contexts\Alliance\Policies\AllianceCapacityPolicy;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateMembershipStatus
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private MembershipAdministrationGuard $guard,
        private AllianceCapacityPolicy $entitlements,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $membershipId,
        MembershipStatus $status,
    ): AllianceMembership {
        if (! in_array($status, [MembershipStatus::Active, MembershipStatus::Suspended, MembershipStatus::Removed], true)) {
            throw ValidationException::withMessages([
                'status' => 'This membership state is not available through administration.',
            ]);
        }

        return DB::transaction(function () use ($alliance, $actor, $membershipId, $status): AllianceMembership {
            // Activation can consume Alliance-wide member capacity; suspension/removal
            // only mutate one membership and therefore use the narrow lifecycle boundary.
            $context = $status === MembershipStatus::Active
                ? $this->authority->requireExclusive($actor, $alliance, AlliancePermission::MembershipManage)
                : $this->authority->require($actor, $alliance, AlliancePermission::MembershipManage);

            if ($status === MembershipStatus::Active) {
                // Identity-binding operations use Player -> membership everywhere in
                // the repository. Route to the Player first without locking, then lock
                // Player before the target membership so Kingdom transfer/invitation
                // workflows cannot invert the order.
                $routing = AllianceMembership::query()
                    ->select(['id', 'player_id'])
                    ->where('id', $membershipId)
                    ->where('alliance_id', $context->alliance->id)
                    ->firstOrFail();

                $player = Player::query()
                    ->whereKey($routing->player_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $membership = AllianceMembership::query()
                    ->where('id', $routing->id)
                    ->where('alliance_id', $context->alliance->id)
                    ->where('player_id', $player->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                $membership = AllianceMembership::query()
                    ->where('id', $membershipId)
                    ->where('alliance_id', $context->alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $player = null;
            }

            $this->guard->assertCanManage($context, $membership);

            if ($status !== MembershipStatus::Active) {
                $this->guard->assertCanDeactivate($membership);
            }

            $previousStatus = $membership->status;

            if ($status === MembershipStatus::Active) {
                if ($previousStatus !== MembershipStatus::Active) {
                    $this->entitlements->assertMemberCapacity($context->alliance);
                }

                if (! $player instanceof Player
                    || (string) $player->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                    throw ValidationException::withMessages([
                        'status' => 'The Player must belong to the Alliance Kingdom before this membership can be activated.',
                    ]);
                }

                $otherActiveMembership = AllianceMembership::query()
                    ->where('player_id', $membership->player_id)
                    ->where('status', MembershipStatus::Active->value)
                    ->where('id', '<>', $membership->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($otherActiveMembership instanceof AllianceMembership) {
                    throw ValidationException::withMessages([
                        'status' => 'The Player already has an active Alliance membership.',
                    ]);
                }
            }

            if ($previousStatus === $status) {
                return $membership;
            }

            $membership->forceFill([
                'status' => $status,
                'rank' => $status === MembershipStatus::Active && $previousStatus === MembershipStatus::Removed
                    ? AllianceRank::R1
                    : $membership->rank,
                'joined_at' => $status === MembershipStatus::Active
                    ? ($membership->joined_at ?? now())
                    : $membership->joined_at,
                'left_at' => $status === MembershipStatus::Removed ? now() : null,
            ])->save();

            if ($status === MembershipStatus::Removed) {
                $membership->roles()->detach();
            }

            $metadata = [
                'previous_status' => $previousStatus->value,
                'status' => $status->value,
                'player_id' => (string) $membership->player_id,
            ];

            $this->audit->record('membership.status_changed', $context->actor, $membership, $context->alliance, $metadata);

            OutboxMessage::query()->create([
                'alliance_id' => $context->alliance->id,
                'partition_key' => 'alliance:'.$context->alliance->id,
                'event_type' => 'membership.status_changed',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.status_changed:'.$membership->id.':'.$status->value.':'.now()->format('Uu'),
                'payload' => [
                    'alliance_id' => $context->alliance->id,
                    'membership_id' => $membership->id,
                    'player_id' => $membership->player_id,
                    'previous_status' => $previousStatus->value,
                    'status' => $status->value,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh();
        });
    }
}
