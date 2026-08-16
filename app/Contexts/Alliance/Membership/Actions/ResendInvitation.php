<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use App\Contexts\Alliance\Policies\AllianceCapacityPolicy;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ResendInvitation
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
        private AllianceCapacityPolicy $entitlements,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $invitationId): IssuedInvitation
    {
        return DB::transaction(function () use ($alliance, $actor, $invitationId): IssuedInvitation {
            // Resend can revive an expired invitation and therefore reserve member
            // capacity again; serialize it with other capacity-sensitive membership writes.
            $context = $this->allianceWriteState->lockExclusiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::InvitationManage);

            $invitation = Invitation::query()
                ->where('id', $invitationId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($invitation->status, [InvitationStatus::Accepted, InvitationStatus::Revoked], true)) {
                throw ValidationException::withMessages([
                    'invitation' => 'Accepted or revoked invitations cannot be resent.',
                ]);
            }

            $target = Player::query()
                ->whereKey($invitation->player_id)
                ->lockForUpdate()
                ->firstOrFail();

            $roster = AllianceRosterEntry::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('player_id', $target->id)
                ->where('state', RosterState::Active->value)
                ->sharedLock()
                ->first();

            if (! $roster instanceof AllianceRosterEntry
                || (string) $target->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'invitation' => 'The invited Player is no longer active on this Alliance roster.',
                ]);
            }

            if (AllianceMembership::query()
                ->where('player_id', $target->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => 'The invited Player is already active in an Alliance.',
                ]);
            }

            if ($target->user_id !== null) {
                $ownerEmail = User::query()->whereKey($target->user_id)->value('email');
                if (! is_string($ownerEmail)
                    || ! hash_equals(Str::lower($ownerEmail), Str::lower((string) $invitation->email))) {
                    throw ValidationException::withMessages([
                        'invitation' => 'The invited Player is now owned by a different account.',
                    ]);
                }
            }

            $alreadyConsumesCapacity = $invitation->status === InvitationStatus::Pending
                && $invitation->expires_at !== null
                && $invitation->expires_at->isFuture();

            if (! $alreadyConsumesCapacity) {
                $this->entitlements->assertMemberCapacity($context->alliance);
            }

            $token = $this->tokens->issue();
            $ttlHours = max(1, (int) config('alliance.invitation_ttl_hours', 72));

            $invitation->forceFill([
                'token_hash' => $this->tokens->hash($token),
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addHours($ttlHours),
                'revoked_at' => null,
            ])->save();

            $this->audit->record('invitation.resent', $context->actor, $invitation, $context->alliance, [
                'player_id' => (string) $invitation->player_id,
            ]);

            OutboxMessage::query()->create([
                'alliance_id' => $context->alliance->id,
                'partition_key' => 'alliance:'.$context->alliance->id,
                'event_type' => 'invitation.resent',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.resent:'.$invitation->id.':'.hash('sha256', $token),
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $context->alliance->id,
                    'player_id' => $invitation->player_id,
                    'email' => $invitation->email,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return new IssuedInvitation((string) $invitation->id, $token);
        });
    }
}
