<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use Illuminate\Support\Str;

final class RecordAccountSession
{
    public function handle(int $userId, string $sessionId, string $userAgent): void
    {
        if ($userId <= 0 || $sessionId === '') {
            return;
        }

        $hash = hash('sha256', $sessionId);
        [$browser, $platform, $device] = $this->deviceSummary($userAgent);

        $session = AccountSession::query()->firstOrNew([
            'user_id' => $userId,
            'session_id_hash' => $hash,
        ]);

        if (! $session->exists) {
            $session->forceFill([
                'public_id' => (string) Str::uuid(),
                'session_id' => $sessionId,
                'first_seen_at' => now(),
            ]);
        }

        $session->forceFill([
            'session_id' => $sessionId,
            'browser_family' => $browser,
            'platform_family' => $platform,
            'device_family' => $device,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();
    }

    /** @return array{0:string,1:string,2:string} */
    private function deviceSummary(string $userAgent): array
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown platform',
        };

        $device = match (true) {
            str_contains($userAgent, 'iPad'), str_contains($userAgent, 'Tablet') => 'Tablet',
            str_contains($userAgent, 'Mobile'), str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'Android') => 'Mobile',
            default => 'Desktop',
        };

        return [$browser, $platform, $device];
    }
}
