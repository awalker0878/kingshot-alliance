<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Policies\MemberCapacityPolicy;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
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
        private MemberCapacityPolicy $entitlements,
        private PlayerReferenceQuery $players,
        private AccountIdentityQuery $accounts,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $invitationId): IssuedInvitation
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $invitationId): IssuedInvitation {
            $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::InvitationManage);

            $invitation = Invitation::query()->whereKey($invitationId)->where('alliance_id', $context->alliance->id)->lockForUpdate()->firstOrFail();
            if (in_array($invitation->status, [InvitationStatus::Accepted, InvitationStatus::Revoked], true)) {
                throw ValidationException::withMessages(['invitation' => 'Accepted or revoked invitations cannot be resent.']);
            }

            $target = $this->players->require((string) $invitation->player_id);
            $roster = AllianceRosterEntry::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('player_id', $target->playerId)
                ->where('state', RosterState::Active->value)
                ->sharedLock()
                ->first();

            if (! $roster instanceof AllianceRosterEntry || $target->kingdomId !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages(['invitation' => 'The invited Player is no longer active on this Alliance roster.']);
            }

            if (AllianceMembership::query()->where('player_id', $target->playerId)->where('status', MembershipStatus::Active->value)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['invitation' => 'The invited Player is already active in an Alliance.']);
            }

            if ($target->userId !== null) {
                $owner = $this->accounts->require($target->userId);
                if (! hash_equals(Str::lower($owner->email), Str::lower((string) $invitation->email))) {
                    throw ValidationException::withMessages(['invitation' => 'The invited Player is now owned by a different account.']);
                }
            }

            $alreadyConsumesCapacity = $invitation->status === InvitationStatus::Pending
                && $invitation->expires_at !== null
                && $invitation->expires_at->isFuture();
            if (! $alreadyConsumesCapacity) {
                $this->entitlements->assertCapacity($context->alliance);
            }

            $token = $this->tokens->issue();
            $ttlHours = max(1, (int) config('alliance.invitation_ttl_hours', 72));
            $invitation->forceFill([
                'token_hash' => $this->tokens->hash($token),
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addHours($ttlHours),
                'revoked_at' => null,
            ])->save();

            $this->audit->record('invitation.resent', $context->actor, $invitation, $context->alliance, ['player_id' => (string) $invitation->player_id]);
            OutboxMessage::query()->create([
                'alliance_id' => $context->alliance->id,
                'partition_key' => 'alliance:'.$context->alliance->id,
                'event_type' => 'invitation.resent',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.resent:'.$invitation->id.':'.hash('sha256', $token),
                'payload' => ['invitation_id' => $invitation->id, 'alliance_id' => $context->alliance->id, 'player_id' => $invitation->player_id, 'email' => $invitation->email],
                'occurred_at' => now(), 'available_at' => now(), 'attempts' => 0,
            ]);

            return new IssuedInvitation((string) $invitation->id, $token);
        });
    }
}
