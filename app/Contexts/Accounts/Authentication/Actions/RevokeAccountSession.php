<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class RevokeAccountSession
{
    public function __construct(
        private SessionManager $sessions,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $userId, string $publicId, string $currentSessionId): void
    {
        $sessionId = DB::transaction(function () use ($userId, $publicId, $currentSessionId): string {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $record = AccountSession::query()
                ->where('user_id', $userId)
                ->where('public_id', $publicId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->firstOrFail();

            $currentHash = hash('sha256', $currentSessionId);
            if (hash_equals($record->session_id_hash, $currentHash)) {
                throw ValidationException::withMessages([
                    'session' => 'The current session cannot be revoked from this action.',
                ]);
            }

            $record->forceFill(['revoked_at' => now()])->save();
            $user->forceFill(['remember_token' => Str::random(60)])->save();

            $this->audit->record(
                event: 'auth.session.revoked',
                actor: $user,
                subject: $user,
                metadata: ['session_public_id' => $record->public_id],
            );

            return (string) $record->session_id;
        });

        // An outer credential transaction must commit before raw storage is touched.
        DB::afterCommit(function () use ($sessionId): void {
            try {
                $this->sessions->driver()->getHandler()->destroy($sessionId);
            } catch (Throwable $exception) {
                // The durable marker already denies access; storage expires normally.
                report($exception);
            }
        });
    }
}
