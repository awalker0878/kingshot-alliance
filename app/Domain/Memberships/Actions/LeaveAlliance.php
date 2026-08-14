<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Services\MembershipAdministrationGuard;
use App\Domain\Platform\Models\OutboxMessage;
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
            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
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

            $this->audit->record('membership.left', $player, $membership, $alliance);

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'partition_key' => 'alliance:'.$alliance->id,
                'event_type' => 'membership.left',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.left:'.$membership->id.':'.Str::ulid(),
                'payload' => [
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'player_id' => $player->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh();
        });
    }
}
