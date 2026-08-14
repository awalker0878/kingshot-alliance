<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAllianceRank
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $membershipId,
        AllianceRank $rank,
    ): AllianceMembership {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RoleManage)) {
            throw new AuthorizationException;
        }

        if ($rank === AllianceRank::R5) {
            throw ValidationException::withMessages([
                'rank' => 'Use Alliance leadership transfer to assign R5.',
            ]);
        }

        return DB::transaction(function () use ($alliance, $actor, $membershipId, $rank): AllianceMembership {
            $currentAlliance = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->firstOrFail();
            $lockedActor = Player::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! $this->authorization->allowsForUpdate($lockedActor, $currentAlliance, PermissionKey::RoleManage)) {
                throw new AuthorizationException;
            }

            $locked = AllianceMembership::query()
                ->whereKey($membershipId)
                ->where('alliance_id', $currentAlliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->player_id === (string) $lockedActor->id) {
                throw ValidationException::withMessages([
                    'rank' => 'The active Player cannot change its own rank through rank administration.',
                ]);
            }

            if ($locked->rank === AllianceRank::R5) {
                throw ValidationException::withMessages([
                    'rank' => 'Use Alliance leadership transfer to change the current R5.',
                ]);
            }

            $previousRank = $locked->rank;

            if ($previousRank === $rank) {
                return $locked;
            }

            $locked->forceFill(['rank' => $rank])->save();

            $metadata = [
                'membership_id' => $locked->id,
                'player_id' => $locked->player_id,
                'previous_rank' => $previousRank->value,
                'rank' => $rank->value,
            ];

            $this->audit->record('membership.rank_changed', $lockedActor, $locked, $currentAlliance, $metadata);
            $this->outbox->record('membership.rank_changed', (string) $currentAlliance->id, $locked, $metadata);

            return $locked->refresh();
        });
    }
}
