<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Session\SessionManager;

final readonly class RevokeOtherAccountSessions
{
    public function __construct(
        private SessionManager $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $userId, string $currentSessionId): int
    {
        $user = User::query()->findOrFail($userId);
        $currentHash = hash('sha256', $currentSessionId);
        $records = AccountSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('session_id_hash', '!=', $currentHash)
            ->get();

        foreach ($records as $record) {
            $this->sessions->driver()->getHandler()->destroy((string) $record->session_id);
            $record->forceFill(['revoked_at' => now()])->save();
        }

        $count = $records->count();
        $this->audit->record(
            event: 'auth.sessions.revoked',
            actor: $user,
            subject: $user,
            metadata: ['count' => $count],
        );

        return $count;
    }
}
