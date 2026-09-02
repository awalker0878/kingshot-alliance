<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PasskeySignInMethodController extends Controller
{
    public function update(
        Request $request,
        AccountPasskey $passkey,
        AuditRecorder $audit,
        SecurityNotificationService $securityNotifications,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless((int) $passkey->user_id === (int) $user->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim((string) $validated['name']);
        if ($name === '') {
            return back()->withErrors(['name' => 'Passkey name is required.']);
        }

        $passkey->forceFill(['name' => $name])->save();

        $audit->record(
            event: 'account.passkey.renamed',
            actor: $user,
            subject: $user,
            metadata: ['passkey_public_id' => (string) $passkey->public_id],
        );
        $securityNotifications->publish(
            userId: (int) $user->id,
            event: 'account.passkey.renamed',
            title: (string) __('accounts.security.passkey_renamed.title'),
            body: (string) __('accounts.security.passkey_renamed.body'),
            idempotencyKey: 'account.passkey.renamed:'.$user->id.':'.$passkey->public_id.':'.sha1($name),
        );

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('passkey-renamed'));
    }
}
