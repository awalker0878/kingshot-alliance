<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class AuthorizeOtherSessionRevocation
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

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

            return $locked;
        });
    }
}
