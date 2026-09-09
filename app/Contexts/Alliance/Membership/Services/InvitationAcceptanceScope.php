<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Services;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Data\PendingInvitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class InvitationAcceptanceScope
{
    public function __construct(
        private FindPendingInvitation $invitations,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    public function lock(string $token): PendingInvitation
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Invitation scope must be locked inside a database transaction.');
        }

        $candidate = $this->invitations->byToken($token);
        if ($candidate === null) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
        }

        $alliance = Alliance::query()->whereKey($candidate->allianceId)->sharedLock()->firstOrFail();
        if ($alliance->status !== AllianceStatus::Active) {
            throw ValidationException::withMessages(['invitation' => 'This Alliance is not currently active.']);
        }

        try {
            $this->kingdoms->lockActiveShared((string) $alliance->kingdom_id);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['invitation' => 'The Alliance Kingdom is archived or unavailable.']);
        }

        return $candidate;
    }
}
