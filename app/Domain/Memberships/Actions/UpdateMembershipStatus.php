<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Services\MembershipAdministrationGuard;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Services\PlanEntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateMembershipStatus
{
    public function __construct(
        private MembershipAdministrationGuard $guard,
        private PlanEntitlementService $entitlements,
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
            $currentAlliance = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->firstOrFail();
            $lockedActor = Player::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $membership = AllianceMembership::query()
                ->where('id', $membershipId)
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->guard->assertCanManage($lockedActor, $currentAlliance, $membership);

            if ($status !== MembershipStatus::Active) {
                $this->guard->assertCanDeactivate($membership);
            }

            $previousStatus = $membership->status;

            if ($status === MembershipStatus::Active) {
                if ($previousStatus !== MembershipStatus::Active) {
                    $this->entitlements->assertMemberCapacity($currentAlliance);
                }

                $player = Player::query()->whereKey($membership->player_id)->lockForUpdate()->firstOrFail();
                if ((string) $player->current_kingdom_id !== (string) $currentAlliance->kingdom_id) {
                    throw ValidationException::withMessages([
                        'status' => 'The Player must belong to the Alliance Kingdom before this membership can be activated.',
                    ]);
                }

                if (AllianceMembership::query()
                    ->where('player_id', $membership->player_id)
                    ->where('status', MembershipStatus::Active->value)
                    ->where('id', '<>', $membership->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'The Player already has an active Alliance membership.',
                    ]);
                }
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

            $this->audit->record('membership.status_changed', $lockedActor, $membership, $currentAlliance, $metadata);

            OutboxMessage::query()->create([
                'alliance_id' => $currentAlliance->id,
                'partition_key' => 'alliance:'.$currentAlliance->id,
                'event_type' => 'membership.status_changed',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.status_changed:'.$membership->id.':'.$status->value.':'.now()->format('Uu'),
                'payload' => [
                    'alliance_id' => $currentAlliance->id,
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
