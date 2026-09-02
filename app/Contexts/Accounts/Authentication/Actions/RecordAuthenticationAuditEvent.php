<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;

final readonly class RecordAuthenticationAuditEvent
{
    public function __construct(private AuditRecorder $audit) {}

    /** @param array<string, mixed> $metadata */
    public function handle(int $userId, string $event, array $metadata = []): void
    {
        $user = User::query()->findOrFail($userId);

        $this->audit->record(
            event: $event,
            actor: $user,
            subject: $user,
            metadata: $metadata,
        );
    }
}
