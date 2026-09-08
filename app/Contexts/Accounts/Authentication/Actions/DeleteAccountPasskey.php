<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Passkey;

final class DeleteAccountPasskey extends DeletePasskey
{
    public function __construct(private readonly AccountSignInMethodPolicy $methods) {}

    public function __invoke(Authenticatable $user, Passkey $passkey): void
    {
        abort_unless($user instanceof User && $passkey instanceof AccountPasskey, 403);

        DB::transaction(function () use ($user, $passkey): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedPasskey = AccountPasskey::query()->whereKey($passkey->id)
                ->where('user_id', $lockedUser->id)->lockForUpdate()->firstOrFail();

            if (! $this->methods->canRemovePasskey($lockedUser, (int) $lockedPasskey->id)) {
                throw ValidationException::withMessages([
                    'passkey' => 'Add another sign-in method before removing this passkey.',
                ]);
            }

            $lockedPasskey->delete();

            PasskeyDeleted::dispatch($lockedUser, $lockedPasskey);
        });
    }
}
