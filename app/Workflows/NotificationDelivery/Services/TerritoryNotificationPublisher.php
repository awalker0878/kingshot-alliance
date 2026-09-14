<?php

declare(strict_types=1);

namespace App\Workflows\NotificationDelivery\Services;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationQueueReceipt;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryNotificationEligibilityQuery;
use Carbon\CarbonImmutable;

final readonly class TerritoryNotificationPublisher
{
    public function __construct(private PlayerReferenceQuery $players, private TerritoryNotificationEligibilityQuery $eligibility,
        private NotificationDeliveryService $delivery) {}

    /** @param array{id:string,plan_id:string,kind:string} $activity */
    public function publish(array $activity, string $playerId): ?NotificationQueueReceipt
    {
        $player = $this->players->find($playerId);
        if ($player === null || $player->userId === null || $player->canonicalPlayerId !== null) {
            return null;
        }
        $metadata = ['plan_id' => $activity['plan_id'], 'kind' => $activity['kind']];
        $source = new NotificationSource('territory.activity', $player->userId, $playerId, 'territory_activity', $activity['id'], $metadata);
        if (! $this->eligibility->allows($source, $player)) {
            return null;
        }
        $title = match ($activity['kind']) {
            'published' => 'Territory plan published', 'reviewed' => 'Territory plan reviewed',
            'assigned' => 'Territory position assigned', 'review_requested' => 'Territory review requested',
            default => null,
        };
        if ($title === null) {
            return null;
        }

        return $this->delivery->queue(new NotificationIntent(
            notificationType: 'territory.activity', recipientUserId: $player->userId, playerId: $playerId,
            availableAt: CarbonImmutable::now('UTC'),
            idempotencyKey: 'territory.activity:'.$activity['id'].':'.$playerId,
            title: $title, body: 'Open the plan to inspect the saved change and current access.',
            actionUrl: '/territory/'.$activity['plan_id'], subjectType: 'territory_activity', subjectId: $activity['id'], metadata: $metadata,
        ));
    }
}
