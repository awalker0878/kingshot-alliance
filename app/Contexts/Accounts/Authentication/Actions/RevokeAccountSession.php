<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Session\SessionManager;
use Illuminate\Validation\ValidationException;

final readonly class RevokeAccountSession
{
    public function __construct(
        private SessionManager $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $userId, string $publicId, string $currentSessionId): void
    {
        $user = User::query()->findOrFail($userId);
        $record = AccountSession::query()
            ->where('user_id', $userId)
            ->where('public_id', $publicId)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $currentHash = hash('sha256', $currentSessionId);
        if (hash_equals($record->session_id_hash, $currentHash)) {
            throw ValidationException::withMessages([
                'session' => 'The current session cannot be revoked from this action.',
            ]);
        }

        $this->sessions->driver()->getHandler()->destroy((string) $record->session_id);
        $record->forceFill(['revoked_at' => now()])->save();

        $this->audit->record(
            event: 'auth.session.revoked',
            actor: $user,
            subject: $user,
            metadata: ['session_public_id' => $record->public_id],
        );
    }
}
