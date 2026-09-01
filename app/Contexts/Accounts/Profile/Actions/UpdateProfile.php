<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProfile
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $userId, string $name, string $timezone): void
    {
        DB::transaction(function () use ($userId, $name, $timezone): void {
            $currentUser = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $values = [
                'name' => $name,
                'timezone' => $timezone,
            ];
            $changedFields = [];

            foreach ($values as $field => $value) {
                if ((string) $currentUser->getAttribute($field) !== (string) $value) {
                    $changedFields[] = $field;
                }
            }

            if ($changedFields === []) {
                return;
            }

            $currentUser->forceFill($values)->save();

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
        });
    }
}
