<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Services\MembershipAdministrationGuard;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
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
