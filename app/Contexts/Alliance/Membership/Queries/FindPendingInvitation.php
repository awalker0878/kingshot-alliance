<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;

final readonly class FindPendingInvitation
{
    public function __construct(private InvitationTokenService $tokens) {}

    public function byToken(string $token): ?Invitation
    {
        if ($token === '') {
            return null;
        }

        return Invitation::query()
            ->where('token_hash', $this->tokens->hash($token))
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->with('alliance')
            ->first();
    }
}
