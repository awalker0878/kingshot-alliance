<?php

declare(strict_types=1);

namespace App\Workflows\NotificationDelivery\Actions;

use App\Contexts\Operations\TerritoryPlanning\Actions\AdvanceTerritoryNotificationActivity;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryNotificationEligibilityQuery;
use App\Workflows\NotificationDelivery\Services\TerritoryNotificationPublisher;
use Illuminate\Support\Facades\DB;

final readonly class QueueTerritoryNotifications
{
    public function __construct(private TerritoryNotificationEligibilityQuery $activities,
        private AdvanceTerritoryNotificationActivity $advance, private TerritoryNotificationPublisher $publisher) {}

    /** Bounded committed source pages, exactly-once delivery intents; transport remains with Communications.
     * @return array{pages:int,recipients:int,queued:int}
     */
    public function handle(int $maxPages = 10, int $pageSize = 50): array
    {
        $result = ['pages' => 0, 'recipients' => 0, 'queued' => 0];
        for ($index = 0; $index < max(1, min(20, $maxPages)); $index++) {
            $receipt = DB::transaction(function () use ($pageSize): ?array {
                $activity = $this->activities->lockNextPage($pageSize);
                if ($activity === null) {
                    return null;
                }
                $queued = 0;
                foreach ($activity['recipient_player_ids'] as $playerId) {
                    $message = $this->publisher->publish($activity, $playerId);
                    if ($message?->createdMessage === true) {
                        $queued++;
                    }
                }
                $this->advance->handle($activity['id'], $activity['after'], $activity['next_after'], $activity['complete']);

                return ['recipients' => count($activity['recipient_player_ids']), 'queued' => $queued];
            });
            if ($receipt === null) {
                break;
            }
            $result['pages']++;
            $result['recipients'] += $receipt['recipients'];
            $result['queued'] += $receipt['queued'];
        }

        return $result;
    }
}
