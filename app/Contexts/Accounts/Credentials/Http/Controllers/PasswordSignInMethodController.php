<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Http\Controllers;

use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Credentials\Actions\AddPassword;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

final class PasswordSignInMethodController extends Controller
{
    public function store(
        Request $request,
        AddPassword $addPassword,
        RecentAuthentication $recentAuthentication,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $addPassword->handle((int) $user->id, (string) $validated['password'], $request->session()->getId());
        $recentAuthentication->clear($request);

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('password-added'));
    }

    public function destroy(
        Request $request,
        RemovePassword $removePassword,
        RecentAuthentication $recentAuthentication,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $removePassword->handle((int) $user->id, $request->session()->getId());
        $recentAuthentication->clear($request);

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('password-removed'));
    }
}
