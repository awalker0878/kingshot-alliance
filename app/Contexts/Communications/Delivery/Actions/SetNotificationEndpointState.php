<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SetNotificationEndpointState
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        int $recipientUserId,
        string $playerId,
        string $endpointId,
        bool $enabled,
    ): void {
        DB::transaction(function () use ($recipientUserId, $playerId, $endpointId, $enabled): void {
            $actor = $this->players->lockCurrent($playerId);
            if ($actor->userId !== $recipientUserId) {
                throw ValidationException::withMessages(['player' => 'The active Governor no longer belongs to this account.']);
            }

            $endpoint = NotificationEndpoint::query()
                ->whereKey($endpointId)
                ->where('recipient_user_id', $recipientUserId)
                ->where('player_id', $playerId)
                ->lockForUpdate()
                ->firstOrFail();

            $endpoint->forceFill([
                'enabled' => $enabled,
                'health_status' => $enabled
                    ? EndpointHealthStatus::NeverTested
                    : EndpointHealthStatus::Paused,
                'last_error' => $enabled ? null : $endpoint->last_error,
            ])->save();

            $this->audit->record(
                $enabled ? 'notification.endpoint.resumed' : 'notification.endpoint.paused',
                $actor,
                $endpoint,
                metadata: [
                    'channel' => $endpoint->channel->value,
                    'label' => $endpoint->label,
                ],
            );
        });
    }
}
