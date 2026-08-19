<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteNotificationEndpoint
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $recipientUserId, string $playerId, string $endpointId): void
    {
        DB::transaction(function () use ($recipientUserId, $playerId, $endpointId): void {
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

            $this->audit->record('notification.endpoint.deleted', $actor, $endpoint, metadata: [
                'channel' => $endpoint->channel->value,
                'label' => $endpoint->label,
            ]);
            $endpoint->delete();
        });
    }
}
