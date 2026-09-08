<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Http\Controllers;

use App\Contexts\Accounts\EmailVerification\Actions\VerifyAccountEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

final class VerifyEmailController extends Controller
{
    public function __construct(private readonly VerifyAccountEmail $verifyEmail) {}

    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->verifyEmail->handle((int) $user->id, (string) $request->route('hash'));

        return redirect()->route('dashboard', ['verified' => 1]);
    }
}
