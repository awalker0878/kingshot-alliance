<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryOutboxMessage
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(AccountIdentity $actor, string $messageId): string
    {
        return DB::transaction(function () use ($actor, $messageId): string {
            $grant = PlatformAdministrator::query()
                ->where('user_id', $actor->userId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();
            if (! $grant instanceof PlatformAdministrator) {
                throw new AuthorizationException('Platform administrator access is required.');
            }

            $message = OutboxMessage::query()->whereKey($messageId)->lockForUpdate()->firstOrFail();
            if ($message->published_at !== null) {
                throw ValidationException::withMessages([
                    'outbox' => 'Published outbox messages cannot be replayed.',
                ]);
            }
            $maximumAttempts = max(1, (int) config('operations.outbox.maximum_attempts', 10));
            if ($message->attempts < $maximumAttempts || $message->last_error === null) {
                throw ValidationException::withMessages([
                    'outbox' => 'Only an exhausted failed outbox message can be released for retry.',
                ]);
            }

            $previousAttempts = $message->attempts;
            $errorFingerprint = substr(hash('sha256', $message->last_error), 0, 16);
            $message->forceFill([
                'attempts' => 0,
                'available_at' => now(),
                'last_error' => null,
            ])->save();

            $this->audit->record(
                'platform.outbox.retry_released',
                $actor,
                $message,
                $message->alliance_id,
                [
                    'event_type' => $message->event_type,
                    'previous_attempts' => $previousAttempts,
                    'error_fingerprint' => $errorFingerprint,
                ],
            );

            return (string) $message->id;
        });
    }
}
