<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Services;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Policies\MemberCapacityPolicy;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Owner-internal invitation implementation. */
final readonly class IssueAllianceInvitation
{
    public function __construct(
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
        private MemberCapacityPolicy $capacity,
        private PlayerReferenceQuery $players,
        private AccountIdentityQuery $accounts,
    ) {}

    public function handle(AllianceMutationContext $context, string $targetPlayerId, string $email): IssuedInvitation
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance invitations must be issued inside an existing database transaction.');
        }

        $email = Str::lower(trim($email));
        $target = $this->players->require($targetPlayerId);

        $roster = AllianceRosterEntry::query()
            ->where('alliance_id', $context->alliance->id)
            ->where('player_id', $targetPlayerId)
            ->where('state', RosterState::Active->value)
            ->sharedLock()
            ->first();

        if (! $roster instanceof AllianceRosterEntry || $target->kingdomId !== (string) $context->alliance->kingdom_id) {
            throw ValidationException::withMessages(['player_id' => 'The invited Player must be active on this Alliance roster.']);
        }

        $activeMembership = AllianceMembership::query()
            ->where('player_id', $targetPlayerId)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($activeMembership instanceof AllianceMembership) {
            throw ValidationException::withMessages([
                'player_id' => (string) $activeMembership->alliance_id === (string) $context->alliance->id
                    ? 'This Player is already an active Alliance member.'
                    : 'This Player is already active in another Alliance.',
            ]);
        }

        if ($target->userId !== null) {
            $owner = $this->accounts->require($target->userId);
            if (! hash_equals(Str::lower($owner->email), $email)) {
                throw ValidationException::withMessages(['email' => 'This Player is already owned by a different account.']);
            }
        }

        $supersededInvitations = Invitation::query()
            ->where('alliance_id', $context->alliance->id)
            ->where(function ($query) use ($targetPlayerId, $email): void {
                $query->where('player_id', $targetPlayerId)->orWhere('email', $email);
            })
            ->where('status', InvitationStatus::Pending->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($supersededInvitations as $superseded) {
            $superseded->forceFill(['status' => InvitationStatus::Revoked, 'revoked_at' => now()])->save();
            $this->audit->record('invitation.revoked', $context->actor, $superseded, $context->alliance, [
                'player_id' => (string) $superseded->player_id,
                'reason' => 'superseded',
            ]);
        }

        $this->capacity->assertCapacity($context->alliance);
        $token = $this->tokens->issue();
        $ttlHours = max(1, (int) config('alliance.invitation_ttl_hours', 72));

        $invitation = Invitation::query()->create([
            'alliance_id' => $context->alliance->id,
            'player_id' => $targetPlayerId,
            'email' => $email,
            'token_hash' => $this->tokens->hash($token),
            'status' => InvitationStatus::Pending,
            'invited_by_player_id' => $context->actor->playerId,
            'expires_at' => now()->addHours($ttlHours),
        ]);

        $this->audit->record('invitation.created', $context->actor, $invitation, $context->alliance, [
            'player_id' => $targetPlayerId,
            'email' => $email,
        ]);
        OutboxMessage::query()->create([
            'alliance_id' => $context->alliance->id,
            'partition_key' => 'alliance:'.$context->alliance->id,
            'event_type' => 'invitation.created',
            'aggregate_type' => Invitation::class,
            'aggregate_id' => $invitation->id,
            'idempotency_key' => 'invitation.created:'.$invitation->id,
            'payload' => ['invitation_id' => $invitation->id, 'alliance_id' => $context->alliance->id, 'player_id' => $targetPlayerId, 'email' => $email],
            'occurred_at' => now(), 'available_at' => now(), 'attempts' => 0,
        ]);

        return new IssuedInvitation((string) $invitation->id, $token);
    }
}
