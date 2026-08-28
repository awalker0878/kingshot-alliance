<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Services;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\QueuedDeliveryBatch;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final readonly class IntelligenceSignalNotificationPublisher
{
    public const NOTIFICATION_TYPE = 'intelligence.change';

    public function __construct(private NotificationDeliveryService $delivery) {}

    /**
     * Queue Communications-owned delivery for an already-authorized derived
     * signal. This service neither retrieves Intelligence nor persists a signal.
     *
     * @param  array<string,mixed>  $signal
     */
    public function publish(
        int $recipientUserId,
        ?string $playerId,
        array $signal,
        string $policyKey = 'default',
    ): QueuedDeliveryBatch {
        $fingerprint = trim((string) ($signal['fingerprint'] ?? ''));
        if ($fingerprint === '') {
            throw new InvalidArgumentException('Intelligence signal delivery requires a deterministic fingerprint.');
        }

        $subjectType = trim((string) ($signal['subjectType'] ?? '')) ?: 'intelligence_signal';
        $subjectId = trim((string) ($signal['subjectId'] ?? '')) ?: $fingerprint;
        $idempotencyKey = hash('sha256', implode('|', [
            self::NOTIFICATION_TYPE,
            $fingerprint,
            (string) $recipientUserId,
            $playerId ?? '-',
            $policyKey,
        ]));

        return $this->delivery->queueEnabledChannelBatch(
            notificationType: self::NOTIFICATION_TYPE,
            recipientUserId: $recipientUserId,
            playerId: $playerId,
            dueAt: Carbon::now(),
            idempotencyKey: $idempotencyKey,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: [
                'signalType' => $signal['type'] ?? null,
                'summary' => $signal['summary'] ?? null,
                'metric' => $signal['metric'] ?? null,
                'state' => $signal['state'] ?? null,
                'observedAt' => $signal['observedAt'] ?? null,
                'sourceClassification' => $signal['sourceClassification'] ?? null,
                'sourceOwner' => $signal['sourceOwner'] ?? null,
                'canonicalUrl' => $signal['canonicalUrl'] ?? null,
                'signalFingerprint' => $fingerprint,
                'ruleVersion' => $signal['ruleVersion'] ?? null,
                'policyKey' => $policyKey,
            ],
        );
    }
}
