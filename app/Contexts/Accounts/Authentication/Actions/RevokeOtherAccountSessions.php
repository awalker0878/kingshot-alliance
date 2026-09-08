<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RevokeOtherAccountSessions
{
    public function __construct(
        private SessionManager $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $userId, string $currentSessionId): int
    {
        $sessionIds = DB::transaction(function () use ($userId, $currentSessionId): array {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $lastRegisteredId = AccountSession::query()->where('user_id', $userId)->max('id') ?? 0;
            $records = AccountSession::query()
                ->where('user_id', $userId)
                ->where('id', '<=', $lastRegisteredId)
                ->whereNull('revoked_at')
                ->where('session_id_hash', '!=', hash('sha256', $currentSessionId))
                ->lockForUpdate()
                ->lazyById(100);

            $sessionIds = [];
            foreach ($records as $record) {
                $record->forceFill(['revoked_at' => now()])->save();
                $sessionIds[] = (string) $record->session_id;
            }

            $user->forceFill(['remember_token' => Str::random(60)])->save();
            $this->audit->record(
                event: 'auth.sessions.revoked',
                actor: $user,
                subject: $user,
                metadata: ['count' => count($sessionIds)],
            );

            return $sessionIds;
        });

        foreach ($sessionIds as $sessionId) {
            $this->sessions->driver()->getHandler()->destroy($sessionId);
        }

        return count($sessionIds);
    }
}
