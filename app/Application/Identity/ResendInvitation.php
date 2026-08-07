<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\InvitationStatus;
use App\Models\Alliance;
use App\Models\Invitation;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ResendInvitation
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private InvitationTokenService $tokens,
        private AuditRecorder $audit,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $invitationId): IssuedInvitation
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::InvitationManage)) {
            throw new AuthorizationException();
        }

        return DB::transaction(function () use ($alliance, $actor, $invitationId): IssuedInvitation {
            $invitation = Invitation::query()
                ->where('id', $invitationId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($invitation->status, [InvitationStatus::Accepted, InvitationStatus::Revoked], true)) {
                throw ValidationException::withMessages([
                    'invitation' => 'Accepted or revoked invitations cannot be resent.',
                ]);
            }

            $token = $this->tokens->issue();
            $ttlHours = max(1, (int) config('identity.invitation_ttl_hours', 72));

            $invitation->forceFill([
                'token_hash' => $this->tokens->hash($token),
                'status' => InvitationStatus::Pending,
                'expires_at' => now()->addHours($ttlHours),
                'revoked_at' => null,
            ])->save();

            $this->audit->record(
                event: 'invitation.resent',
                actor: $actor,
                subject: $invitation,
                alliance: $alliance,
                metadata: ['email' => $invitation->email],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'invitation.resent',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.resent:'.$invitation->id.':'.hash('sha256', $token),
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $alliance->id,
                    'email' => $invitation->email,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return new IssuedInvitation($invitation->refresh(), $token);
        });
    }
}
