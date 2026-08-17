<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Data\PendingInvitation;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;

final readonly class FindPendingInvitation
{
    public function __construct(private InvitationTokenService $tokens) {}

    public function byToken(string $token): ?PendingInvitation
    {
        if ($token === '') {
            return null;
        }

        $invitation = Invitation::query()
            ->where('token_hash', $this->tokens->hash($token))
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->with('alliance:id,name')
            ->first();

        if (! $invitation instanceof Invitation || $invitation->alliance === null) {
            return null;
        }

        return new PendingInvitation(
            invitationId: (string) $invitation->id,
            allianceId: (string) $invitation->alliance_id,
            allianceName: (string) $invitation->alliance->name,
            playerId: (string) $invitation->player_id,
            email: (string) $invitation->email,
            expiresAt: $invitation->expires_at?->toIso8601String(),
        );
    }
}
