<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Services\IssueAllianceInvitation;
use App\Contexts\Alliance\Membership\ValueObjects\IssuedInvitation;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateInvitation
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private IssueAllianceInvitation $issuer,
    ) {}

    public function handle(Alliance $alliance, Player $actor, Player $target, string $email): IssuedInvitation
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($alliance, $actor, $target, $email): IssuedInvitation {
            // Invitation creation reserves Alliance-wide member capacity.
            $context = $this->allianceWriteState->lockExclusiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::InvitationManage);

            return $this->issuer->handle($context, $target, $email);
        });
    }
}
