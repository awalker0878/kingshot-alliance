<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Services\IssueAllianceInvitation;
use App\Domain\Memberships\ValueObjects\IssuedInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateInvitation
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private IssueAllianceInvitation $issuer,
    ) {}

    public function handle(Alliance $alliance, Player $actor, Player $target, string $email): IssuedInvitation
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($alliance, $actor, $target, $email): IssuedInvitation {
            // Invitation creation reserves Alliance-wide member capacity.
            $context = $this->authority->requireExclusive(
                $actor,
                $alliance,
                PermissionKey::InvitationManage,
            );

            return $this->issuer->handle($context, $target, $email);
        });
    }
}
