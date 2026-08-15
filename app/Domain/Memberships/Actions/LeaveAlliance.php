<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Services\MembershipAdministrationGuard;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class LeaveAlliance
{
    public function __construct(
        private MembershipAdministrationGuard $guard,
        private AuditRecorder $audit,
    ) {}

    public function handle(Alliance $alliance, Player $player): AllianceMembership
    {
        return DB::transaction(function () use ($alliance, $player): AllianceMembership {
            // Leaving is Player-self lifecycle, not Alliance-management authority.
            // Still serialize against Alliance lifecycle/transfer operations first.
            $currentAlliance = Alliance::query()
                ->whereKey($alliance->id)
                ->sharedLock()
                ->firstOrFail();

            $membership = AllianceMembership::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('player_id', $player->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->firstOrFail();

            $this->guard->assertCanDeactivate($membership);

            $membership->forceFill([
                'status' => MembershipStatus::Left,
                'left_at' => now(),
            ])->save();
            $membership->roles()->detach();

            $this->audit->record('membership.left', $player, $membership, $currentAlliance);

            OutboxMessage::query()->create([
                'alliance_id' => $currentAlliance->id,
                'partition_key' => 'alliance:'.$currentAlliance->id,
                'event_type' => 'membership.left',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.left:'.$membership->id.':'.Str::ulid(),
                'payload' => [
                    'alliance_id' => $currentAlliance->id,
                    'membership_id' => $membership->id,
                    'player_id' => $membership->player_id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh();
        });
    }
}
