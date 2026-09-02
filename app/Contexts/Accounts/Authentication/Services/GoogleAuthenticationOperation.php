<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Services;

use App\Contexts\Accounts\Authentication\Enums\GoogleAuthenticationIntent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class GoogleAuthenticationOperation
{
    private const SESSION_KEY = 'accounts.google_operation';

    private const TTL_SECONDS = 600;

    public function start(
        Request $request,
        GoogleAuthenticationIntent $intent,
        ?int $userId = null,
        ?string $invitationToken = null,
    ): void {
        $request->session()->put(self::SESSION_KEY, [
            'intent' => $intent->value,
            'user_id' => $userId,
            'invitation_token' => $invitationToken,
            'started_at' => now()->timestamp,
        ]);
    }

    /** @return array{intent:GoogleAuthenticationIntent,user_id:?int,invitation_token:?string} */
    public function consume(Request $request): array
    {
        $operation = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($operation)) {
            throw $this->invalid();
        }

        $intent = GoogleAuthenticationIntent::tryFrom((string) ($operation['intent'] ?? ''));
        $startedAt = (int) ($operation['started_at'] ?? 0);
        if ($intent === null || $startedAt < now()->timestamp - self::TTL_SECONDS) {
            throw $this->invalid();
        }

        $userId = $operation['user_id'] ?? null;
        $invitationToken = $operation['invitation_token'] ?? null;

        return [
            'intent' => $intent,
            'user_id' => is_numeric($userId) ? (int) $userId : null,
            'invitation_token' => is_string($invitationToken) && $invitationToken !== '' ? $invitationToken : null,
        ];
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'google' => 'This Google sign-in request expired or was already used. Please start again.',
        ]);
    }
}
