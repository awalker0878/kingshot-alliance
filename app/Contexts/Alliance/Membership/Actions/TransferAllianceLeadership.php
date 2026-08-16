<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class TransferAllianceLeadership
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $targetPlayerId): void
    {
        DB::transaction(function () use ($alliance, $actor, $targetPlayerId): void {
            // Leadership is a genuine Alliance-wide invariant: lifecycle first,
            // then every active membership in deterministic key order.
            $lockedAlliance = Alliance::query()
                ->whereKey($alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAlliance->status !== AllianceStatus::Active) {
                throw ValidationException::withMessages([
                    'alliance' => 'Leadership can only be transferred for an active Alliance.',
                ]);
            }

            $activeMemberships = AllianceMembership::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $actorMembership = $activeMemberships->first(
                static fn (AllianceMembership $membership): bool => (string) $membership->player_id === (string) $actor->id,
            );

            if (! $actorMembership instanceof AllianceMembership || $actorMembership->rank !== AllianceRank::R5) {
                throw new AuthorizationException;
            }

            $leaders = $activeMemberships->filter(
                static fn (AllianceMembership $membership): bool => $membership->rank === AllianceRank::R5,
            );

            if ($leaders->count() !== 1) {
                throw new RuntimeException('An Alliance must have exactly one active R5 before leadership transfer.');
            }

            $target = $activeMemberships->first(
                static fn (AllianceMembership $membership): bool => (string) $membership->player_id === (string) $targetPlayerId,
            );
            if (! $target instanceof AllianceMembership) {
                throw ValidationException::withMessages([
                    'player_id' => 'The new R5 must be an active Player in this Alliance.',
                ]);
            }

            if ((string) $target->player_id === (string) $actor->id) {
                return;
            }

            $actorMembership->forceFill(['rank' => AllianceRank::R4])->save();
            $target->forceFill(['rank' => AllianceRank::R5])->save();

            $metadata = [
                'previous_r5_player_id' => (string) $actorMembership->player_id,
                'new_r5_player_id' => (string) $target->player_id,
                'previous_r5_new_rank' => AllianceRank::R4->value,
            ];

            $this->audit->record('alliance.leadership_transferred', $actor, $lockedAlliance, $lockedAlliance, $metadata);
            $this->outbox->record('alliance.leadership_transferred', (string) $lockedAlliance->id, $lockedAlliance, [
                'alliance_id' => (string) $lockedAlliance->id,
                ...$metadata,
            ]);
        });
    }
}
