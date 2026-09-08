<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final readonly class ConfirmAccountPassword
{
    public function __construct(
        private AuditRecorder $audit,
        private RecentAuthentication $recentAuthentication,
    ) {}

    public function handle(Request $request, #[SensitiveParameter] string $password): void
    {
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($userId), 401);

        DB::transaction(function () use ($request, $userId, $password): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->ensureActive();
            abort_unless($user->supportsPasswordAuthentication(), 403);

            if (! Hash::check($password, (string) $user->getAuthPassword())) {
                throw ValidationException::withMessages(['password' => 'The provided password is incorrect.']);
            }

            $this->audit->record(event: 'auth.password.confirmed', actor: $user, subject: $user);
            $passwordFingerprint = hash('sha256', (string) $user->getAuthPassword());
            DB::afterCommit(function () use ($request, $userId, $passwordFingerprint): void {
                $current = User::query()->find($userId);
                if ($current instanceof User && $current->isActive() && $current->supportsPasswordAuthentication()
                    && hash_equals($passwordFingerprint, hash('sha256', (string) $current->getAuthPassword()))
                    && (string) $request->user()?->getAuthIdentifier() === (string) $userId) {
                    $this->recentAuthentication->mark($request, 'password');
                }
            });
        });
    }
}
