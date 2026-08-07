<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

final readonly class VerifyEmailController extends Controller
{
    public function __construct(private AuditRecorder $audit) {}

    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $user->hasVerifiedEmail()) {
            $request->fulfill();
            $user->refresh();

            $this->audit->record(
                event: 'auth.email.verified',
                actor: $user,
                subject: $user,
            );
        }

        return redirect()->route('dashboard', ['verified' => 1]);
    }
}
