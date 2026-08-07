<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Services\MembershipAdministrationGuard;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateMembershipStatus
{
    public function __construct(
        private MembershipAdministrationGuard $guard,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $membershipId,
        MembershipStatus $status,
    ): AllianceMembership {
        if (! in_array($status, [MembershipStatus::Active, MembershipStatus::Suspended, MembershipStatus::Removed], true)) {
            throw ValidationException::withMessages([
                'status' => 'This membership state is not available through administration.',
            ]);
        }

        return DB::transaction(function () use ($alliance, $actor, $membershipId, $status): AllianceMembership {
            $membership = AllianceMembership::query()
                ->where('id', $membershipId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->guard->assertCanManage($actor, $alliance, $membership);

            if ($status !== MembershipStatus::Active) {
                $this->guard->assertCanChangeOwnerMembership($membership);
            }

            $previousStatus = $membership->status;

            $membership->forceFill([
                'status' => $status,
                'joined_at' => $status === MembershipStatus::Active
                    ? ($membership->joined_at ?? now())
                    : $membership->joined_at,
                'left_at' => $status === MembershipStatus::Removed ? now() : null,
            ])->save();

            if ($status === MembershipStatus::Removed) {
                $membership->roles()->detach();
            } elseif ($status === MembershipStatus::Active && ! $membership->roles()->exists()) {
                $memberRole = Role::query()
                    ->where('alliance_id', $alliance->id)
                    ->where('key', DefaultAllianceRole::Member->value)
                    ->first();

                if (! $memberRole instanceof Role) {
                    throw new LogicException('The default member role was not provisioned.');
                }

                $membership->roles()->attach($memberRole->id, [
                    'alliance_id' => $alliance->id,
                ]);
            }

            $this->audit->record(
                event: 'membership.status_changed',
                actor: $actor,
                subject: $membership,
                alliance: $alliance,
                metadata: [
                    'previous_status' => $previousStatus->value,
                    'status' => $status->value,
                    'user_id' => $membership->user_id,
                ],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'membership.status_changed',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.status_changed:'.$membership->id.':'.$status->value.':'.now()->format('Uu'),
                'payload' => [
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'user_id' => $membership->user_id,
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
