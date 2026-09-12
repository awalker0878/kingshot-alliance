<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Responses;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class AccountPasskeyLoginResponse implements PasskeyLoginResponse
{
    public function toResponse($request): Response
    {
        abort_unless($request instanceof Request, 500);

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $target = redirect()->intended((string) config('passkeys.redirect', '/dashboard'))->getTargetUrl();

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $target]);
        }

        return redirect()->to($target);
    }
}
