<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\IntelligenceSignals;

use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalNotificationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class IntelligenceSignalNotificationPublisherV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_same_signal_policy_and_recipient_queues_delivery_exactly_once(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $publisher = app(IntelligenceSignalNotificationPublisher::class);
        $signal = [
            'type' => 'observation_change',
            'subjectType' => 'tracked_alliance',
            'subjectId' => 'tracked-abc',
            'metric' => 'power',
            'summary' => 'ABC Alliance was observed at 4.2B power, up from 3.9B.',
            'observedAt' => '2026-08-22T00:00:00Z',
            'sourceClassification' => 'observation',
            'sourceOwner' => 'Intelligence/Observations',
            'canonicalUrl' => '/alliance/kingdom-alliances/tracked-abc/history',
            'fingerprint' => hash('sha256', 'signal-abc-power'),
            'ruleVersion' => '1',
        ];

        $first = $publisher->publish($account->userId, $player->playerId, $signal, 'material-alliance-change');
        $second = $publisher->publish($account->userId, $player->playerId, $signal, 'material-alliance-change');

        self::assertSame($first->deliveryIds, $second->deliveryIds);
        self::assertSame(1, NotificationDelivery::query()
            ->where('notification_type', IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE)
            ->count());
        $delivery = NotificationDelivery::query()
            ->where('notification_type', IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE)
            ->firstOrFail();
        self::assertSame($signal['fingerprint'], $delivery->metadata['signalFingerprint']);
        self::assertSame('Intelligence/Observations', $delivery->metadata['sourceOwner']);
    }
}
