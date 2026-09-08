<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Credentials\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use LogicException;
use SensitiveParameter;

final readonly class RequestPasswordReset
{
    public function __construct(private Timebox $timebox) {}

    public function handle(string $email): void
    {
        $email = Str::lower(trim($email));

        $this->timebox->call(static fn () => DB::transaction(static function () use ($email): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if (! $user instanceof User || ! $user->isActive() || ! $user->supportsPasswordAuthentication()) {
                return;
            }

            Password::sendResetLink(['email' => $email], static function (User $resolved, #[SensitiveParameter] string $token) use ($user): void {
                if ((int) $resolved->id !== (int) $user->id) {
                    throw new LogicException('The reset broker resolved a different account.');
                }
                $user->sendPasswordResetNotification($token);
            });
        }), 200000);
    }
}
