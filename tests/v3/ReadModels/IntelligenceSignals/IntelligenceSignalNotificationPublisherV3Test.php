<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\IntelligenceSignals;

use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalNotificationPublisher;
use Illuminate\Auth\Access\AuthorizationException;
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
        $alliance = $scenarios->alliance($player);
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

        $first = $publisher->publish(
            $account->userId,
            $player->playerId,
            $alliance->allianceId,
            $signal,
            'material-alliance-change',
        );
        $second = $publisher->publish(
            $account->userId,
            $player->playerId,
            $alliance->allianceId,
            $signal,
            'material-alliance-change',
        );

        self::assertSame($first->messageId, $second->messageId);
        self::assertSame($first->deliveryIds, $second->deliveryIds);
        self::assertSame(1, NotificationMessage::query()
            ->where('notification_type', IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE)
            ->count());
        $message = NotificationMessage::query()
            ->where('notification_type', IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE)
            ->firstOrFail();
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        self::assertSame($signal['fingerprint'], $metadata['signalFingerprint'] ?? null);
        self::assertSame('Intelligence/Observations', $metadata['sourceOwner'] ?? null);
        self::assertSame($alliance->allianceId, $metadata['alliance_id'] ?? null);
        self::assertSame($signal['summary'], $message->body);
        self::assertSame(1, NotificationDelivery::query()
            ->where('notification_message_id', $message->id)
            ->count());

        $changed = $signal;
        $changed['fingerprint'] = hash('sha256', 'signal-abc-power-changed');
        $changed['summary'] = 'ABC Alliance was observed at 4.3B power.';
        $publisher->publish(
            $account->userId,
            $player->playerId,
            $alliance->allianceId,
            $changed,
            'material-alliance-change',
        );
        self::assertSame(2, NotificationMessage::query()
            ->where('notification_type', IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE)
            ->count());

        $this->expectException(AuthorizationException::class);
        $publisher->publish(
            $account->userId + 1,
            $player->playerId,
            $alliance->allianceId,
            $changed,
        );
    }
}
