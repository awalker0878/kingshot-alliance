<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateProfile
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $userId, string $name, string $email, string $timezone): bool
    {
        $email = Str::lower(trim($email));

        try {
            $emailChanged = DB::transaction(function () use ($userId, $name, $email, $timezone): bool {
                $currentUser = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $emailChanged = ! hash_equals(Str::lower((string) $currentUser->email), $email);
                $values = [
                    'name' => $name,
                    'email' => $email,
                    'timezone' => $timezone,
                ];
                $changedFields = [];

                foreach ($values as $field => $value) {
                    if ((string) $currentUser->getAttribute($field) !== (string) $value) {
                        $changedFields[] = $field;
                    }
                }

                if ($changedFields === []) {
                    return false;
                }

                $currentUser->forceFill([
                    ...$values,
                    'email_verified_at' => $emailChanged ? null : $currentUser->email_verified_at,
                ])->save();

                $this->audit->record(
                    event: 'profile.updated',
                    actor: $currentUser,
                    subject: $currentUser,
                    metadata: ['changed_fields' => $changedFields],
                );

                OutboxMessage::query()->create([
                    'alliance_id' => null,
                    'event_type' => 'profile.updated',
                    'aggregate_type' => User::class,
                    'aggregate_id' => (string) $currentUser->id,
                    'idempotency_key' => 'profile.updated:'.$currentUser->id.':'.now()->format('Uu'),
                    'payload' => [
                        'user_id' => $currentUser->id,
                        'changed_fields' => $changedFields,
                    ],
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'attempts' => 0,
                ]);

                return $emailChanged;
            });
        } catch (QueryException $exception) {
            if (User::query()
                ->where('email', $email)
                ->where('id', '<>', $userId)
                ->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'The email has already been taken.',
                ]);
            }

            throw $exception;
        }

        if ($emailChanged) {
            User::query()->findOrFail($userId)->sendEmailVerificationNotification();
        }

        return $emailChanged;
    }
}
