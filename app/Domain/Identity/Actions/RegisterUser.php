<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Services\AuditRecorder;

use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RegisterUser
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(string $name, string $email, string $password, string $timezone = 'UTC'): User
    {
        return DB::transaction(function () use ($name, $email, $password, $timezone): User {
            $user = User::query()->create([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $password,
                'timezone' => $timezone,
            ]);

            $this->audit->record(
                event: 'user.registered',
                actor: $user,
                subject: $user,
                metadata: ['timezone' => $user->timezone],
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
    }
}
