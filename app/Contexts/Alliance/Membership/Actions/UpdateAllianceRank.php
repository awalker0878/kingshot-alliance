<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAllianceRank
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $membershipId,
        AllianceRank $rank,
    ): string {
        if ($rank === AllianceRank::R5) {
            throw ValidationException::withMessages([
                'rank' => 'Use Alliance leadership transfer to assign R5.',
            ]);
        }

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $membershipId, $rank): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RoleManage);

            $locked = AllianceMembership::query()
                ->whereKey($membershipId)
                ->where('alliance_id', $context->alliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->player_id === (string) $context->actor->playerId) {
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
                return (string) $locked->id;
            }

            $locked->forceFill(['rank' => $rank])->save();

            $metadata = [
                'membership_id' => $locked->id,
                'player_id' => $locked->player_id,
                'previous_rank' => $previousRank->value,
                'rank' => $rank->value,
            ];

            $this->audit->record('membership.rank_changed', $context->actor, $locked, $context->alliance, $metadata);
            $this->outbox->record('membership.rank_changed', (string) $context->alliance->id, $locked, $metadata);

            return (string) $locked->id;
        });
    }
}
