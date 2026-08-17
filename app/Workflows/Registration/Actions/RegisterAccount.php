<?php

declare(strict_types=1);

namespace App\Workflows\Registration\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Registration\Actions\RegisterUser;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use Illuminate\Support\Facades\DB;

final readonly class RegisterAccount
{
    public function __construct(
        private RegisterUser $registerUser,
        private AcceptInvitation $acceptInvitation,
    ) {}

    /** @return array{user: User, membership: AllianceMembership|null} */
    public function handle(
        string $name,
        string $email,
        string $password,
        string $timezone,
        ?Invitation $invitation,
        ?string $invitationToken,
    ): array {
        $result = DB::transaction(function () use (
            $name,
            $email,
            $password,
            $timezone,
            $invitation,
            $invitationToken,
        ): array {
            $user = $this->registerUser->handle(
                name: $name,
                email: $email,
                password: $password,
                timezone: $timezone,
            );

            $membership = $invitation instanceof Invitation && is_string($invitationToken)
                ? $this->acceptInvitation->handle((int) $user->id, (string) $user->email, $invitationToken)
                : null;

            return [
                'user' => $user,
                'membership' => $membership,
            ];
        });

        $result['user']->sendEmailVerificationNotification();

        return $result;
    }
}
