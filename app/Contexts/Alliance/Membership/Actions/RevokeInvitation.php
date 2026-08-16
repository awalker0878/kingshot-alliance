<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RevokeInvitation
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $invitationId): Invitation
    {
        return DB::transaction(function () use ($alliance, $actor, $invitationId): Invitation {
            $context = $this->authority->require($actor, $alliance, AlliancePermission::InvitationManage);

            $invitation = Invitation::query()
                ->where('id', $invitationId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->status !== InvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only pending invitations can be revoked.',
                ]);
            }

            $invitation->forceFill([
                'status' => InvitationStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            $this->audit->record('invitation.revoked', $context->actor, $invitation, $context->alliance, [
                'player_id' => (string) $invitation->player_id,
            ]);

            OutboxMessage::query()->create([
                'alliance_id' => $context->alliance->id,
                'partition_key' => 'alliance:'.$context->alliance->id,
                'event_type' => 'invitation.revoked',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.revoked:'.$invitation->id,
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $context->alliance->id,
                    'player_id' => $invitation->player_id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $invitation->refresh();
        });
    }
}
