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
        return $this->summary($account)['count'];
    }

    /** @return array{password:bool,google:bool,passkeys:int,count:int,canRemovePassword:bool,canDisconnectGoogle:bool,canRemoveOwnedPasskey:bool} */
    public function summary(int|User $account): array
    {
        $user = $this->user($account);
        $password = $this->hasPassword($user);
        $google = $this->hasGoogle($user);
        $passkeys = $this->passkeyCount($user);
        $count = ($password ? 1 : 0) + ($google ? 1 : 0) + $passkeys;

        return [
            'password' => $password,
            'google' => $google,
            'passkeys' => $passkeys,
            'count' => $count,
            'canRemovePassword' => $password && $count > 1,
            'canDisconnectGoogle' => $google && $count > 1,
            'canRemoveOwnedPasskey' => $passkeys > 0 && $count > 1,
        ];
    }

    public function canRemovePassword(int|User $account): bool
    {
        return $this->summary($account)['canRemovePassword'];
    }

    public function canDisconnectGoogle(int|User $account): bool
    {
        return $this->summary($account)['canDisconnectGoogle'];
    }

    public function canRemovePasskey(int|User $account, int $passkeyId): bool
    {
        $user = $this->user($account);

        return DB::table('passkeys')
            ->where('id', $passkeyId)
            ->where('user_id', $user->id)
            ->exists()
            && $this->summary($user)['canRemoveOwnedPasskey'];
    }

    private function user(int|User $account): User
    {
        return $account instanceof User ? $account : User::query()->findOrFail($account);
    }
}
