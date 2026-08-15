<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Services;

use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use App\Shared\Messaging\Models\OutboxMessage;
use App\Contexts\Alliance\Policies\AllianceCapacityPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Memberships-owned invitation persistence rules for callers that already hold
 * an exclusive Alliance mutation boundary. This service never starts a transaction
 * or acquires Alliance authority itself; callers must do both before invoking it.
 */
final readonly class IssueAllianceInvitation
{
    public function __construct(
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
        private AllianceCapacityPolicy $entitlements,
    ) {}

    public function handle(
        AllianceMutationContext $context,
        Player $target,
        string $email,
    ): IssuedInvitation {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance invitations must be issued inside an existing database transaction.');
        }

        $email = Str::lower(trim($email));
        $lockedTarget = Player::query()
            ->whereKey($target->id)
            ->lockForUpdate()
            ->firstOrFail();

        $roster = AllianceRosterEntry::query()
            ->where('alliance_id', $context->alliance->id)
            ->where('player_id', $lockedTarget->id)
            ->where('state', RosterState::Active->value)
            ->sharedLock()
            ->first();

        if (! $roster instanceof AllianceRosterEntry
            || (string) $lockedTarget->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
            throw ValidationException::withMessages([
                'player_id' => 'The invited Player must be active on this Alliance roster.',
            ]);
        }

        $activeMembership = AllianceMembership::query()
            ->where('player_id', $lockedTarget->id)
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

        if ($lockedTarget->user_id !== null) {
            $ownerEmail = User::query()->whereKey($lockedTarget->user_id)->value('email');
            if (! is_string($ownerEmail) || ! hash_equals(Str::lower($ownerEmail), $email)) {
                throw ValidationException::withMessages([
                    'email' => 'This Player is already owned by a different account.',
                ]);
            }
        }

        $supersededInvitations = Invitation::query()
            ->where('alliance_id', $context->alliance->id)
            ->where(function ($query) use ($lockedTarget, $email): void {
                $query->where('player_id', $lockedTarget->id)->orWhere('email', $email);
            })
            ->where('status', InvitationStatus::Pending->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($supersededInvitations as $superseded) {
            $superseded->forceFill([
                'status' => InvitationStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('invitation.revoked', $context->actor, $superseded, $context->alliance, [
                'player_id' => (string) $superseded->player_id,
                'reason' => 'superseded',
            ]);
        }

        // Caller must hold the exclusive Alliance mutation boundary because this is
        // a parent-wide capacity invariant shared with membership activation.
        $this->entitlements->assertMemberCapacity($context->alliance);

        $token = $this->tokens->issue();
        $ttlHours = max(1, (int) config('alliance.invitation_ttl_hours', 72));

        $invitation = Invitation::query()->create([
            'alliance_id' => $context->alliance->id,
            'player_id' => $lockedTarget->id,
            'email' => $email,
            'token_hash' => $this->tokens->hash($token),
            'status' => InvitationStatus::Pending,
            'invited_by_player_id' => $context->actor->id,
            'expires_at' => now()->addHours($ttlHours),
        ]);

        $this->audit->record('invitation.created', $context->actor, $invitation, $context->alliance, [
            'player_id' => (string) $lockedTarget->id,
            'email' => $email,
        ]);

        OutboxMessage::query()->create([
            'alliance_id' => $context->alliance->id,
            'partition_key' => 'alliance:'.$context->alliance->id,
            'event_type' => 'invitation.created',
            'aggregate_type' => Invitation::class,
            'aggregate_id' => $invitation->id,
            'idempotency_key' => 'invitation.created:'.$invitation->id,
            'payload' => [
                'invitation_id' => $invitation->id,
                'alliance_id' => $context->alliance->id,
                'player_id' => $lockedTarget->id,
                'email' => $email,
            ],
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ]);

        return new IssuedInvitation((string) $invitation->id, $token);
    }
}
