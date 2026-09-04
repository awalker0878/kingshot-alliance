<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Responses;

use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class AccountPasskeyLoginResponse implements PasskeyLoginResponse
{
    public function __construct(
        private RecentAuthentication $recentAuthentication,
        private AuditRecorder $audit,
    ) {}

    public function toResponse($request): Response
    {
        abort_unless($request instanceof Request, 500);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $passkeyPublicId = trim((string) $request->session()->pull('accounts.passkey_verified_public_id', ''));
        $this->recentAuthentication->mark(
            $request,
            'passkey',
            $passkeyPublicId === '' ? null : $passkeyPublicId,
        );

        $this->audit->record(
            event: 'auth.login',
            actor: $user,
            subject: $user,
            metadata: [
                'provider' => 'passkey',
                'mfa_method' => 'user_verifying_passkey',
            ],
        );

        $target = redirect()->intended((string) config('passkeys.redirect', '/dashboard'))->getTargetUrl();

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $target]);
        }

        return redirect()->to($target);
    }
}
