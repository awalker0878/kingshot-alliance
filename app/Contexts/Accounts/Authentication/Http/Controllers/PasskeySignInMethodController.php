<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\RenameAccountPasskey;
use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PasskeySignInMethodController extends Controller
{
    public function update(
        Request $request,
        string $passkey,
        RenameAccountPasskey $renamePasskey,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim((string) $validated['name']);
        if ($name === '') {
            return back()->withErrors(['name' => 'Passkey name is required.']);
        }

        $renamePasskey->handle(
            userId: (int) $user->getAuthIdentifier(),
            publicId: $passkey,
            name: $name,
        );

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('passkey-renamed'));
    }
}
