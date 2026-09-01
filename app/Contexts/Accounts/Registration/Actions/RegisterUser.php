<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Registration\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Registration\Data\RegisteredAccount;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RegisterUser
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        string $name,
        string $email,
        string $password,
        string $timezone = 'UTC',
        bool $emailVerified = false,
    ): RegisteredAccount {
        $user = DB::transaction(function () use ($name, $email, $password, $timezone, $emailVerified): User {
            $user = User::query()->create([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'timezone' => $timezone,
            ]);

            if ($emailVerified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->audit->record(
                event: 'user.registered',
                actor: $user,
                subject: $user,
                metadata: [
                    'timezone' => $user->timezone,
                    'email_verified_at_registration' => $emailVerified,
                ],
            );

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'user.registered',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'user.registered:'.$user->id,
                'payload' => ['user_id' => $user->id],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $user;
        });

        if (! $emailVerified) {
            $user->sendEmailVerificationNotification();
        }

        return new RegisteredAccount(
            userId: (int) $user->id,
            email: (string) $user->email,
        );
    }
}
