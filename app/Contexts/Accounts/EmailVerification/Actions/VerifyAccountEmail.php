<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

final readonly class VerifyAccountEmail
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $userId, string $hash): void
    {
        DB::transaction(function () use ($userId, $hash): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->ensureActive();
            abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

            if ($user->hasVerifiedEmail()) {
                return;
            }

            $user->markEmailAsVerified();
            $this->audit->record(event: 'auth.email.verified', actor: $user, subject: $user);
            DB::afterCommit(static fn () => event(new Verified($user)));
        });
    }
}
