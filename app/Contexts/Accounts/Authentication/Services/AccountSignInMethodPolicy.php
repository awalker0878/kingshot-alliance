<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Services;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class AccountSignInMethodPolicy
{
    public function hasPassword(int|User $account): bool
    {
        return $this->user($account)->supportsPasswordAuthentication();
    }

    public function hasGoogle(int|User $account): bool
    {
        return $this->user($account)->supportsGoogleAuthentication();
    }

    public function passkeyCount(int|User $account): int
    {
        $userId = (int) $this->user($account)->id;

        return DB::table('passkeys')->where('user_id', $userId)->count();
    }

    public function usableMethodCount(int|User $account): int
    {
        $user = $this->user($account);

        return ($this->hasPassword($user) ? 1 : 0)
            + ($this->hasGoogle($user) ? 1 : 0)
            + $this->passkeyCount($user);
    }

    /** @return array{password:bool,google:bool,passkeys:int,count:int} */
    public function summary(int|User $account): array
    {
        $user = $this->user($account);
        $password = $this->hasPassword($user);
        $google = $this->hasGoogle($user);
        $passkeys = $this->passkeyCount($user);

        return [
            'password' => $password,
            'google' => $google,
            'passkeys' => $passkeys,
            'count' => ($password ? 1 : 0) + ($google ? 1 : 0) + $passkeys,
        ];
    }

    public function canRemovePassword(int|User $account): bool
    {
        $user = $this->user($account);

        return $this->hasPassword($user) && $this->usableMethodCount($user) > 1;
    }

    public function canDisconnectGoogle(int|User $account): bool
    {
        $user = $this->user($account);

        return $this->hasGoogle($user) && $this->usableMethodCount($user) > 1;
    }

    public function canRemovePasskey(int|User $account, int $passkeyId): bool
    {
        $user = $this->user($account);

        return DB::table('passkeys')
            ->where('id', $passkeyId)
            ->where('user_id', $user->id)
            ->exists()
            && $this->usableMethodCount($user) > 1;
    }

    private function user(int|User $account): User
    {
        return $account instanceof User ? $account : User::query()->findOrFail($account);
    }
}
