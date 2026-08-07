<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class LeaveAlliance
{
    public function __construct(
        private MembershipAdministrationGuard $guard,
        private AuditRecorder $audit,
    ) {}

    public function handle(Alliance $alliance, User $user): AllianceMembership
    {
        return DB::transaction(function () use ($alliance, $user): AllianceMembership {
            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->firstOrFail();

            $this->guard->assertCanChangeOwnerMembership($membership);

            $membership->forceFill([
                'status' => MembershipStatus::Left,
                'left_at' => now(),
            ])->save();
            $membership->roles()->detach();

            $this->audit->record(
                event: 'membership.left',
                actor: $user,
                subject: $membership,
                alliance: $alliance,
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'membership.left',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.left:'.$membership->id.':'.Str::ulid(),
                'payload' => [
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'user_id' => $user->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh();
        });
    }
}
