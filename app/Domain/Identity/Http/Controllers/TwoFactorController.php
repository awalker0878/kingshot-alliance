<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Services\TwoFactorManager;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TwoFactorController extends Controller
{
    public function begin(Request $request, TwoFactorManager $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $setup = $twoFactor->begin($user);

        return redirect()->route('profile.show')->with('twoFactorSetup', $setup);
    }

    public function confirm(Request $request, TwoFactorManager $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $recoveryCodes = $twoFactor->confirm($user, (string) $validated['code']);

        return redirect()->route('profile.show')->with('twoFactorRecoveryCodes', $recoveryCodes);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorManager $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $recoveryCodes = $twoFactor->regenerateRecoveryCodes($user);

        return redirect()->route('profile.show')->with('twoFactorRecoveryCodes', $recoveryCodes);
    }

    public function destroy(Request $request, TwoFactorManager $twoFactor): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $twoFactor->disable($user);

        return redirect()->route('profile.show')->with('status', 'two-factor-disabled');
    }
}
