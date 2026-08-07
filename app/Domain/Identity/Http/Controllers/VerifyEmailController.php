<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

final class VerifyEmailController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

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
