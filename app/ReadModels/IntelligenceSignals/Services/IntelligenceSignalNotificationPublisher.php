<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Services;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationQueueReceipt;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final readonly class IntelligenceSignalNotificationPublisher
{
    public const NOTIFICATION_TYPE = 'intelligence.change';

    public function __construct(
        private NotificationDeliveryService $delivery,
        private PlayerReferenceQuery $players,
        private AllianceIntelligenceAuthorization $authorization,
    ) {}

    /** @param array<string,mixed> $signal */
    public function publish(
        int $recipientUserId,
        string $playerId,
        string $allianceId,
        array $signal,
        string $policyKey = 'default',
    ): NotificationQueueReceipt {
        $player = $this->players->find($playerId);
        if ($player === null
            || $player->userId !== $recipientUserId
            || ! $this->authorization->allows($playerId, $allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $fingerprint = trim((string) ($signal['fingerprint'] ?? ''));
        if ($fingerprint === '') {
            throw new InvalidArgumentException('Intelligence signal delivery requires a deterministic fingerprint.');
        }
        $subjectType = trim((string) ($signal['subjectType'] ?? '')) ?: 'intelligence_signal';
        $subjectId = trim((string) ($signal['subjectId'] ?? '')) ?: $fingerprint;
        $summary = trim((string) ($signal['summary'] ?? ''));
        $canonicalUrl = trim((string) ($signal['canonicalUrl'] ?? ''));
        if ($summary === '') {
            throw new InvalidArgumentException('Intelligence signal delivery requires a factual summary.');
        }
        if (! str_starts_with($canonicalUrl, '/')) {
            $canonicalUrl = '/alliance/kingdom-alliances/intelligence';
        }
        $idempotencyKey = hash('sha256', implode('|', [
            self::NOTIFICATION_TYPE, $fingerprint, (string) $recipientUserId, $playerId, $allianceId, $policyKey,
        ]));

        return $this->delivery->queue(new NotificationIntent(
            notificationType: self::NOTIFICATION_TYPE,
            recipientUserId: $recipientUserId,
            playerId: $playerId,
            availableAt: CarbonImmutable::now('UTC'),
            idempotencyKey: $idempotencyKey,
            title: 'Intelligence change',
            body: $summary,
            actionUrl: $canonicalUrl,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: [
                'alliance_id' => $allianceId,
                'signalType' => $signal['type'] ?? null,
                'summary' => $summary,
                'metric' => $signal['metric'] ?? null,
                'state' => $signal['state'] ?? null,
                'observedAt' => $signal['observedAt'] ?? null,
                'sourceClassification' => $signal['sourceClassification'] ?? null,
                'sourceOwner' => $signal['sourceOwner'] ?? null,
                'canonicalUrl' => $canonicalUrl,
                'signalFingerprint' => $fingerprint,
                'ruleVersion' => $signal['ruleVersion'] ?? null,
                'policyKey' => $policyKey,
            ],
        ));
    }
}
