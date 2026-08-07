<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\InvitationStatus;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Memberships\Services\InvitationTokenService;
use App\Domain\Memberships\ValueObjects\IssuedInvitation;
use App\Domain\Platform\Models\OutboxMessage;
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
    ) {}

    public function handle(Alliance $alliance, User $actor, string $email): IssuedInvitation
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::InvitationManage)) {
            throw new AuthorizationException;
        }

        $email = Str::lower(trim($email));

        $existingMember = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->whereHas('user', static fn ($query) => $query->where('email', $email))
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        if ($existingMember) {
            throw ValidationException::withMessages([
                'email' => 'This account is already an active alliance member.',
            ]);
        }

        return DB::transaction(function () use ($alliance, $actor, $email): IssuedInvitation {
            Alliance::query()
                ->whereKey($alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $supersededInvitations = Invitation::query()
                ->where('alliance_id', $alliance->id)
                ->where('email', $email)
                ->where('status', InvitationStatus::Pending->value)
                ->lockForUpdate()
                ->get();

            foreach ($supersededInvitations as $superseded) {
                $superseded->forceFill([
                    'status' => InvitationStatus::Revoked,
                    'revoked_at' => now(),
                ])->save();

                $this->audit->record(
                    event: 'invitation.revoked',
                    actor: $actor,
                    subject: $superseded,
                    alliance: $alliance,
                    metadata: [
                        'email' => $email,
                        'reason' => 'superseded',
                    ],
                );

                OutboxMessage::query()->create([
                    'alliance_id' => $alliance->id,
                    'event_type' => 'invitation.revoked',
                    'aggregate_type' => Invitation::class,
                    'aggregate_id' => $superseded->id,
                    'idempotency_key' => 'invitation.revoked:'.$superseded->id.':superseded',
                    'payload' => [
                        'invitation_id' => $superseded->id,
                        'alliance_id' => $alliance->id,
                        'reason' => 'superseded',
                    ],
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'attempts' => 0,
                ]);
            }

            $token = $this->tokens->issue();
            $ttlHours = max(1, (int) config('identity.invitation_ttl_hours', 72));

            $invitation = Invitation::query()->create([
                'alliance_id' => $alliance->id,
                'email' => $email,
                'token_hash' => $this->tokens->hash($token),
                'status' => InvitationStatus::Pending,
                'invited_by_user_id' => $actor->id,
                'expires_at' => now()->addHours($ttlHours),
            ]);

            $this->audit->record(
                event: 'invitation.created',
                actor: $actor,
                subject: $invitation,
                alliance: $alliance,
                metadata: ['email' => $email],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'invitation.created',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.created:'.$invitation->id,
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $alliance->id,
                    'email' => $email,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return new IssuedInvitation($invitation, $token);
        });
    }
}
