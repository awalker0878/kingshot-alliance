<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Services\InvitationTokenService;
use App\Domain\Memberships\ValueObjects\IssuedInvitation;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Services\PlanEntitlementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateInvitation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
        private PlanEntitlementService $entitlements,
    ) {}

    public function handle(Alliance $alliance, Player $actor, Player $target, string $email): IssuedInvitation
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::InvitationManage)) {
            throw new AuthorizationException;
        }

        $email = Str::lower(trim($email));

        $eligible = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $target->id)
            ->where('state', RosterState::Active->value)
            ->exists();

        if (! $eligible || (string) $target->current_kingdom_id !== (string) $alliance->kingdom_id) {
            throw ValidationException::withMessages([
                'player_id' => 'The invited Player must be active on this Alliance roster.',
            ]);
        }

        $activeMembership = AllianceMembership::query()
            ->where('player_id', $target->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if ($activeMembership instanceof AllianceMembership) {
            throw ValidationException::withMessages([
                'player_id' => (string) $activeMembership->alliance_id === (string) $alliance->id
                    ? 'This Player is already an active Alliance member.'
                    : 'This Player is already active in another Alliance.',
            ]);
        }

        if ($target->user_id !== null) {
            $ownerEmail = User::query()->whereKey($target->user_id)->value('email');
            if (! is_string($ownerEmail) || ! hash_equals(Str::lower($ownerEmail), $email)) {
                throw ValidationException::withMessages([
                    'email' => 'This Player is already owned by a different account.',
                ]);
            }
        }

        return DB::transaction(function () use ($alliance, $actor, $target, $email): IssuedInvitation {
            Alliance::query()->whereKey($alliance->id)->lockForUpdate()->firstOrFail();

            $activeMembership = AllianceMembership::query()
                ->where('player_id', $target->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($activeMembership instanceof AllianceMembership) {
                throw ValidationException::withMessages([
                    'player_id' => 'This Player is already active in an Alliance.',
                ]);
            }

            $supersededInvitations = Invitation::query()
                ->where('alliance_id', $alliance->id)
                ->where(function ($query) use ($target, $email): void {
                    $query->where('player_id', $target->id)->orWhere('email', $email);
                })
                ->where('status', InvitationStatus::Pending->value)
                ->lockForUpdate()
                ->get();

            foreach ($supersededInvitations as $superseded) {
                $superseded->forceFill([
                    'status' => InvitationStatus::Revoked,
                    'revoked_at' => now(),
                ])->save();

                $this->audit->record('invitation.revoked', $actor, $superseded, $alliance, [
                    'player_id' => (string) $superseded->player_id,
                    'reason' => 'superseded',
                ]);
            }

            $this->entitlements->assertMemberCapacity($alliance);

            $token = $this->tokens->issue();
            $ttlHours = max(1, (int) config('identity.invitation_ttl_hours', 72));

            $invitation = Invitation::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $target->id,
                'email' => $email,
                'token_hash' => $this->tokens->hash($token),
                'status' => InvitationStatus::Pending,
                'invited_by_player_id' => $actor->id,
                'expires_at' => now()->addHours($ttlHours),
            ]);

            $this->audit->record('invitation.created', $actor, $invitation, $alliance, [
                'player_id' => (string) $target->id,
                'email' => $email,
            ]);

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'partition_key' => 'alliance:'.$alliance->id,
                'event_type' => 'invitation.created',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.created:'.$invitation->id,
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $alliance->id,
                    'player_id' => $target->id,
                    'email' => $email,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return new IssuedInvitation((string) $invitation->id, $token);
        });
    }
}
