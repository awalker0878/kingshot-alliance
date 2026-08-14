<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Services\InvitationTokenService;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AcceptInvitation
{
    public function __construct(
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $user, string $token): AllianceMembership
    {
        return DB::transaction(function () use ($user, $token): AllianceMembership {
            $invitation = Invitation::query()
                ->where('token_hash', $this->tokens->hash($token))
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->with(['alliance', 'player'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals(Str::lower((string) $invitation->email), Str::lower((string) $user->email))) {
                throw new AuthorizationException;
            }

            $alliance = $invitation->alliance;
            $player = $invitation->player;

            if (! $alliance instanceof Alliance || ! $player instanceof Player) {
                throw new LogicException('An invitation must reference an Alliance and Player.');
            }

            if ($alliance->status !== AllianceStatus::Active) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Alliance is not currently active.',
                ]);
            }

            $lockedPlayer = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();

            if ($lockedPlayer->user_id !== null && (int) $lockedPlayer->user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player belongs to another account.',
                ]);
            }

            if ($lockedPlayer->user_id === null) {
                $lockedPlayer->forceFill(['user_id' => $user->id])->save();
            }

            if ((string) $lockedPlayer->current_kingdom_id !== (string) $alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is no longer in the Alliance Kingdom.',
                ]);
            }

            if (! AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $lockedPlayer->id)
                ->where('state', RosterState::Active->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is no longer active on the Alliance roster.',
                ]);
            }

            $otherActiveMembership = AllianceMembership::query()
                ->where('player_id', $lockedPlayer->id)
                ->where('status', MembershipStatus::Active->value)
                ->where('alliance_id', '!=', $alliance->id)
                ->exists();

            if ($otherActiveMembership) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is already active in another Alliance.',
                ]);
            }

            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $lockedPlayer->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                $membership = AllianceMembership::query()->create([
                    'alliance_id' => $alliance->id,
                    'player_id' => $lockedPlayer->id,
                    'status' => MembershipStatus::Active,
                    'rank' => AllianceRank::R1,
                    'joined_at' => now(),
                ]);
            } elseif ($membership->status !== MembershipStatus::Active) {
                $membership->forceFill([
                    'status' => MembershipStatus::Active,
                    'rank' => AllianceRank::R1,
                    'joined_at' => $membership->joined_at ?? now(),
                    'left_at' => null,
                ])->save();
            }

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'invitation.accepted',
                actor: $lockedPlayer,
                subject: $invitation,
                alliance: $alliance,
                metadata: ['membership_id' => $membership->id],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'partition_key' => 'alliance:'.$alliance->id,
                'event_type' => 'invitation.accepted',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.accepted:'.$invitation->id,
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'player_id' => $lockedPlayer->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh()->load(['alliance', 'player']);
        });
    }
}
