<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AnonymizeAccount
{
    public function __construct(private AuditRecorder $audit, private SessionManager $sessions) {}

    public function handle(int $userId, string $requestId): void
    {
        DB::transaction(function () use ($userId, $requestId): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if (! $user instanceof User || $user->anonymized_at !== null) {
                return;
            }

            $originalEmail = (string) $user->email;
            $registeredSessions = AccountSession::query()->where('user_id', $userId)->lockForUpdate()->get();
            foreach ($registeredSessions as $session) {
                $this->sessions->driver()->getHandler()->destroy((string) $session->session_id);
            }

            AccountSession::query()->where('user_id', $userId)->delete();
            $user->tokens()->delete();
            $user->accountIdentities()->delete();
            DB::table('passkeys')->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();

            $user->forceFill([
                'name' => 'Deleted User',
                'email' => 'deleted+'.$user->id.'@invalid.local',
                'email_verified_at' => null,
                'pending_email' => null,
                'pending_email_requested_at' => null,
                'password' => null,
                'timezone' => 'UTC',
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => Str::random(60),
                'deletion_requested_at' => null,
                'anonymized_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'account.anonymized',
                actor: null,
                subject: $user,
                metadata: ['deletion_request_id' => $requestId],
            );
        });
    }
}
