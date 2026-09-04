<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationDigestDispatch;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BuildNotificationDigestDispatches
{
    private const MAX_MEMBERS = 20;

    public function handle(int $limit = 500): int
    {
        $now = CarbonImmutable::now('UTC');
        $ids = NotificationDelivery::query()
            ->leftJoin('notification_digest_members', 'notification_digest_members.notification_delivery_id', '=', 'notification_deliveries.id')
            ->whereNull('notification_digest_members.notification_delivery_id')
            ->where('notification_deliveries.digest_cadence', '!=', DigestCadence::Immediate->value)
            ->where('notification_deliveries.status', DeliveryStatus::Queued->value)
            ->where('notification_deliveries.due_at', '<=', $now)
            ->orderBy('notification_deliveries.due_at')
            ->orderBy('notification_deliveries.id')
            ->limit(max(1, min(2000, $limit)))
            ->pluck('notification_deliveries.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        /** @var array<string,list<string>> $groups */
        $groups = [];
        foreach ($ids as $id) {
            $delivery = NotificationDelivery::query()->whereKey($id)->first();
            if (! $delivery instanceof NotificationDelivery) {
                continue;
            }
            $message = NotificationMessage::query()->whereKey($delivery->notification_message_id)->first();
            if (! $message instanceof NotificationMessage) {
                continue;
            }
            $playerId = $message->player_id;
            if ($delivery->notification_endpoint_id !== null) {
                $endpoint = NotificationEndpoint::query()->whereKey($delivery->notification_endpoint_id)->first();
                $playerId = $endpoint?->player_id ?? $playerId;
            }
            $key = implode('|', [
                (string) $message->recipient_user_id,
                $playerId ?? 'account',
                $delivery->channel->value,
                $delivery->notification_endpoint_id ?? 'native',
                $delivery->digest_cadence->value,
                $delivery->due_at->format('Y-m-d\TH:i:sP'),
            ]);
            $groups[$key][] = $id;
        }

        $created = 0;
        foreach ($groups as $groupKey => $deliveryIds) {
            foreach (array_chunk($deliveryIds, self::MAX_MEMBERS) as $chunk) {
                $created += DB::transaction(function () use ($groupKey, $chunk): int {
                    $deliveries = NotificationDelivery::query()
                        ->whereIn('id', $chunk)
                        ->where('status', DeliveryStatus::Queued->value)
                        ->where('digest_cadence', '!=', DigestCadence::Immediate->value)
                        ->lockForUpdate()
                        ->get();
                    if ($deliveries->isEmpty()) {
                        return 0;
                    }

                    $availableIds = $deliveries
                        ->filter(static fn (NotificationDelivery $delivery): bool => ! DB::table('notification_digest_members')
                            ->where('notification_delivery_id', (string) $delivery->id)
                            ->exists())
                        ->map(static fn (NotificationDelivery $delivery): string => (string) $delivery->id)
                        ->values()
                        ->all();
                    if ($availableIds === []) {
                        return 0;
                    }

                    sort($availableIds);
                    $first = $deliveries->firstWhere('id', $availableIds[0]) ?? $deliveries->first();
                    if (! $first instanceof NotificationDelivery) {
                        return 0;
                    }
                    $message = NotificationMessage::query()->whereKey($first->notification_message_id)->first();
                    if (! $message instanceof NotificationMessage) {
                        return 0;
                    }
                    $playerId = $message->player_id;
                    if ($first->notification_endpoint_id !== null) {
                        $endpoint = NotificationEndpoint::query()->whereKey($first->notification_endpoint_id)->first();
                        $playerId = $endpoint?->player_id ?? $playerId;
                    }

                    $dispatch = NotificationDigestDispatch::query()->firstOrCreate(
                        ['idempotency_key' => hash('sha256', $groupKey.'|'.implode(',', $availableIds))],
                        [
                            'recipient_user_id' => (int) $message->recipient_user_id,
                            'player_id' => $playerId,
                            'channel' => $first->channel->value,
                            'notification_endpoint_id' => $first->notification_endpoint_id,
                            'cadence' => $first->digest_cadence->value,
                            'window_key' => hash('sha256', $groupKey),
                            'status' => DeliveryStatus::Queued->value,
                            'due_at' => $first->due_at,
                            'attempt_count' => 0,
                            'max_attempts' => 5,
                        ],
                    );

                    foreach ($availableIds as $deliveryId) {
                        DB::table('notification_digest_members')->insertOrIgnore([
                            'notification_digest_dispatch_id' => (string) $dispatch->id,
                            'notification_delivery_id' => $deliveryId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    return $dispatch->wasRecentlyCreated ? 1 : 0;
                });
            }
        }

        return $created;
    }
}
