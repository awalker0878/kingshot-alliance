<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Data\AcceptedMembership;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Alliance\Membership\Services\InvitationAcceptanceScope;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\AuditTrail\ValueObjects\AuditPrincipal;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AcceptInvitation
{
    public function __construct(
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
        private AccountIdentityQuery $accounts,
        private InvitationAcceptanceScope $scope,
        private PlayerReferenceQuery $players,
        private PlayerMembershipQuery $memberships,
    ) {}

    public function handle(
        int $userId,
        string $token,
        string $playerId,
    ): AcceptedMembership {
        $tokenHash = $this->tokens->hash($token);

        return DB::transaction(function () use (
            $userId,
            $token,
            $tokenHash,
            $playerId,
        ): AcceptedMembership {
            $account = $this->accounts->lockActive($userId);
            $candidate = $this->scope->lock($token);
            $alliance = Alliance::query()->findOrFail($candidate->allianceId);
            $player = $this->players->lockCurrentShared($playerId);
            if ($player->userId !== $account->userId) {
                throw new AuthorizationException;
            }

            $invitation = Invitation::query()
                ->whereKey($candidate->invitationId)
                ->where('alliance_id', $alliance->id)
                ->where('token_hash', $tokenHash)
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals(Str::lower((string) $invitation->email), Str::lower($account->email))) {
                throw new AuthorizationException;
            }

            if ((string) $invitation->player_id !== $playerId) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation no longer targets the selected Player.',
                ]);
            }

            if ($player->kingdomId !== (string) $alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is no longer in the Alliance Kingdom.',
                ]);
            }

            $roster = AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $playerId)
                ->where('state', RosterState::Active->value)
                ->sharedLock()
                ->first();

            if (! $roster instanceof AllianceRosterEntry) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is no longer active on the Alliance roster.',
                ]);
            }

            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $playerId)
                ->lockForUpdate()
                ->first();

            if ($membership?->status !== MembershipStatus::Active) {
                if ($this->memberships->hasAnyActiveForPlayer($playerId)) {
                    throw ValidationException::withMessages(['invitation' => 'This Player is already active in another Alliance.']);
                }
                try {
                    $membership = DB::transaction(static function () use ($membership, $alliance, $playerId): AllianceMembership {
                        $membership ??= new AllianceMembership(['alliance_id' => $alliance->id, 'player_id' => $playerId]);
                        $membership->forceFill([
                            'status' => MembershipStatus::Active,
                            'rank' => AllianceRank::R1,
                            'joined_at' => $membership->joined_at ?? now(),
                            'left_at' => null,
                        ])->save();

                        return $membership;
                    });
                } catch (UniqueConstraintViolationException $exception) {
                    if (! $this->memberships->hasAnyActiveForPlayer($playerId)) {
                        throw $exception;
                    }
                    throw ValidationException::withMessages(['invitation' => 'This Player is already active in another Alliance.']);
                }
            }

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'invitation.accepted',
                actor: AuditPrincipal::player($playerId, $userId),
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
                    'player_id' => $playerId,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return new AcceptedMembership(
                membershipId: (string) $membership->id,
                allianceId: (string) $alliance->id,
                playerId: $playerId,
            );
        });
    }
}
