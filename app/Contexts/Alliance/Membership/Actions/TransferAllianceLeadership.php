<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class TransferAllianceLeadership
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $targetPlayerId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $targetPlayerId): void {
            $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);

            $activeMemberships = AllianceMembership::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $actorMembership = $activeMemberships->first(
                static fn (AllianceMembership $membership): bool => (string) $membership->player_id === $actorPlayerId,
            );
            if (! $actorMembership instanceof AllianceMembership || $actorMembership->rank !== AllianceRank::R5) {
                throw new AuthorizationException;
            }

            $leaders = $activeMemberships->filter(static fn (AllianceMembership $membership): bool => $membership->rank === AllianceRank::R5);
            if ($leaders->count() !== 1) {
                throw new RuntimeException('An Alliance must have exactly one active R5 before leadership transfer.');
            }

            $target = $activeMemberships->first(
                static fn (AllianceMembership $membership): bool => (string) $membership->player_id === $targetPlayerId,
            );
            if (! $target instanceof AllianceMembership) {
                throw ValidationException::withMessages(['player_id' => 'The new R5 must be an active Player in this Alliance.']);
            }

            if ((string) $target->player_id === $actorPlayerId) {
                return;
            }

            $actorMembership->forceFill(['rank' => AllianceRank::R4])->save();
            $target->forceFill(['rank' => AllianceRank::R5])->save();

            $metadata = [
                'previous_r5_player_id' => (string) $actorMembership->player_id,
                'new_r5_player_id' => (string) $target->player_id,
                'previous_r5_new_rank' => AllianceRank::R4->value,
            ];
            $this->audit->record('alliance.leadership_transferred', $context->actor, $context->alliance, $context->alliance, $metadata);
            $this->outbox->record('alliance.leadership_transferred', (string) $context->alliance->id, $context->alliance, [
                'alliance_id' => (string) $context->alliance->id,
                ...$metadata,
            ]);
        });
    }
}
