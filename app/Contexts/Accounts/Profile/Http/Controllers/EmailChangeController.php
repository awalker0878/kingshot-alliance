<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\PromotePendingAccountEmail;
use App\Contexts\Accounts\Profile\Actions\RequestAccountEmailChange;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class EmailChangeController extends Controller
{
    public function update(Request $request, RequestAccountEmailChange $requestEmailChange): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        $requestEmailChange->handle((int) $user->id, (string) $validated['email']);

        return back()->with('actionReceipt', $this->receipt('email-change-verification-sent'));
    }

    public function verify(
        Request $request,
        string $id,
        string $hash,
        PromotePendingAccountEmail $promotePendingEmail,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(hash_equals((string) $user->id, $id), 403);

        $promotePendingEmail->handle((int) $user->id, $hash);

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('email-changed'));
    }
}
