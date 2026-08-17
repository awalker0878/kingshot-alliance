<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Services\IssueAllianceInvitation;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateInvitation
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private IssueAllianceInvitation $issuer,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $targetPlayerId, string $email): IssuedInvitation
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $targetPlayerId, $email): IssuedInvitation {
            $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::InvitationManage);

            return $this->issuer->handle($context, $targetPlayerId, $email);
        });
    }
}
