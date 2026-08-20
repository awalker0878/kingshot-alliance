<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager;
use App\Shared\Infrastructure\Http\Controller;
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
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
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

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('two-factor-disabled'));
    }
}
