<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class AuthorizeOtherSessionRevocation
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $userId, string $password): void
    {
        DB::transaction(function () use ($userId, $password): void {
            $locked = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if (! Hash::check($password, (string) $locked->password)) {
                throw ValidationException::withMessages([
                    'password' => 'The password is incorrect.',
                ]);
            }

            $this->audit->record(
                event: 'auth.other_sessions.revoked',
                actor: $locked,
                subject: $locked,
            );

        });

        $current = User::query()->findOrFail($userId);
        Auth::setUser($current);
        Auth::logoutOtherDevices($password);
    }
}
