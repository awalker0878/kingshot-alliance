<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class TransferAllianceOwnership
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(User $actor, Alliance $alliance, AllianceMembership $target): void
    {
        if ($target->alliance_id !== $alliance->id || $target->status !== MembershipStatus::Active) {
            throw new InvalidArgumentException('The new owner must be an active member of the alliance.');
        }

        DB::transaction(function () use ($actor, $alliance, $target): void {
            $ownerRole = Role::query()
                ->where('alliance_id', $alliance->id)
                ->where('key', DefaultAllianceRole::Owner->value)
                ->first();
            $leaderRole = Role::query()
                ->where('alliance_id', $alliance->id)
                ->where('key', DefaultAllianceRole::Leader->value)
                ->first();

            if (! $ownerRole instanceof Role || ! $leaderRole instanceof Role) {
                throw new RuntimeException('Alliance owner and leader roles must exist before ownership transfer.');
            }

            $previousOwners = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->whereHas('roles', static fn ($query) => $query->whereKey($ownerRole->id))
                ->lockForUpdate()
                ->get();

            $target->roles()->syncWithoutDetaching([
                $ownerRole->id => ['alliance_id' => $alliance->id],
            ]);

            foreach ($previousOwners as $previousOwner) {
                if ($previousOwner->id === $target->id) {
                    continue;
                }

                $previousOwner->roles()->detach($ownerRole->id);
                $previousOwner->roles()->syncWithoutDetaching([
                    $leaderRole->id => ['alliance_id' => $alliance->id],
                ]);
            }

            $this->audit->record('platform.alliance.ownership-transferred', $actor, $alliance, $alliance, [
                'new_owner_membership_id' => $target->id,
                'previous_owner_membership_ids' => $previousOwners->pluck('id')->all(),
            ]);
            $this->outbox->record('platform.alliance.ownership-transferred', (string) $alliance->id, $alliance, [
                'alliance_id' => $alliance->id,
                'new_owner_membership_id' => $target->id,
            ]);
        });
    }
}
