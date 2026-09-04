<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class QueueNotificationEndpointTest
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $recipientUserId, string $playerId, string $endpointId): string
    {
        return DB::transaction(function () use ($recipientUserId, $playerId, $endpointId): string {
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
            if (! $endpoint->enabled) {
                throw ValidationException::withMessages(['endpoint' => 'Resume this destination before testing it.']);
            }

            $now = CarbonImmutable::now('UTC');
            $recent = NotificationMessage::query()
                ->where('recipient_user_id', $recipientUserId)
                ->where('notification_type', 'communications.endpoint_test')
                ->where('subject_type', 'notification_endpoint')
                ->where('subject_id', $endpointId)
                ->where('created_at', '>=', $now->subMinute())
                ->count();
            if ($recent >= 3) {
                throw ValidationException::withMessages([
                    'endpoint' => 'Endpoint testing is temporarily rate limited.',
                ]);
            }

            $message = NotificationMessage::query()->create([
                'notification_type' => 'communications.endpoint_test',
                'recipient_user_id' => $recipientUserId,
                'player_id' => $playerId,
                'subject_type' => 'notification_endpoint',
                'subject_id' => $endpointId,
                'title' => 'Kingshot Alliance notification test',
                'body' => 'This destination is configured to receive Kingshot Alliance notifications.',
                'action_url' => '/notifications',
                'urgency' => NotificationUrgency::Normal->value,
                'available_at' => $now,
                'idempotency_key' => hash('sha256', implode('|', [
                    'endpoint-test',
                    $endpointId,
                    $now->format('YmdHi'),
                    (string) ($recent + 1),
                ])),
            ]);

            $delivery = NotificationDelivery::query()->create([
                'notification_message_id' => (string) $message->id,
                'channel' => $endpoint->channel->value,
                'notification_endpoint_id' => (string) $endpoint->id,
                'route_target_label' => (string) $endpoint->label,
                'digest_cadence' => DigestCadence::Immediate->value,
                'due_at' => $now,
                'status' => DeliveryStatus::Queued->value,
                'attempt_count' => 0,
                'max_attempts' => 2,
                'idempotency_key' => hash('sha256', 'endpoint-test-delivery:'.$message->id.':'.$endpoint->id),
                'queued_at' => $now,
                'routing_reason' => 'endpoint_test',
            ]);

            $this->audit->record('notification.endpoint.test_queued', $actor, $endpoint, metadata: [
                'channel' => $endpoint->channel->value,
                'label' => $endpoint->label,
                'delivery_id' => (string) $delivery->id,
            ]);

            return (string) $delivery->id;
        });
    }
}
