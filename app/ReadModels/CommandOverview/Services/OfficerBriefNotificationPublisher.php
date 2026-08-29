<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\QueuedDeliveryBatch;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final readonly class OfficerBriefNotificationPublisher
{
    public const NOTIFICATION_TYPE = 'officer.brief';

    /** @var list<string> */
    private const GROUPS = ['daily_officer', 'upcoming_event', 'post_event_closeout'];

    public function __construct(
        private PlayerReferenceQuery $players,
        private AllianceAuthorization $authorization,
        private NotificationDeliveryService $delivery,
    ) {}

    /**
     * Queues delivery from an already-built brief only after rechecking current
     * account and Alliance authority. It never retrieves protected owner facts.
     *
     * @param array<string,mixed> $brief
     */
    public function publish(
        int $recipientUserId,
        string $playerId,
        string $allianceId,
        array $brief,
        string $policyKey = 'default',
    ): QueuedDeliveryBatch {
        $player = $this->players->find($playerId);
        if ($player === null
            || $player->userId !== $recipientUserId
            || ! $this->authorization->allows($playerId, $allianceId, AlliancePermission::MembershipManage)) {
            throw new AuthorizationException;
        }

        $group = trim((string) ($brief['group'] ?? ''));
        $fingerprint = trim((string) ($brief['fingerprint'] ?? ''));
        $canonicalUrl = trim((string) ($brief['canonicalUrl'] ?? ''));
        if (! in_array($group, self::GROUPS, true)
            || $fingerprint === ''
            || ! str_starts_with($canonicalUrl, '/')) {
            throw new InvalidArgumentException('Officer brief delivery requires a supported group, fingerprint and canonical handoff.');
        }
        $count = max(0, (int) ($brief['count'] ?? 0));
        $state = trim((string) ($brief['state'] ?? 'unknown')) ?: 'unknown';
        $owner = trim((string) ($brief['owner'] ?? 'unknown')) ?: 'unknown';
        $title = match ($group) {
            'daily_officer' => 'Daily Officer Brief',
            'upcoming_event' => 'Upcoming Event Brief',
            'post_event_closeout' => 'Post-Event Closeout Brief',
            default => throw new InvalidArgumentException('Unsupported officer brief group.'),
        };
        $body = sprintf(
            '%d factual owner item(s); state: %s; owner: %s.',
            $count,
            str_replace('_', ' ', $state),
            $owner,
        );

        $isDailyPolicy = $group === 'daily_officer'
            && preg_match('/^daily:\\d{4}-\\d{2}-\\d{2}$/D', $policyKey) === 1;
        $meaningKey = $isDailyPolicy ? $policyKey : $fingerprint;
        $idempotencyKey = hash('sha256', implode('|', [
            self::NOTIFICATION_TYPE,
            $meaningKey,
            (string) $recipientUserId,
            $playerId,
            $allianceId,
        ]));

        return $this->delivery->queueEnabledChannelBatch(
            notificationType: self::NOTIFICATION_TYPE,
            recipientUserId: $recipientUserId,
            playerId: $playerId,
            dueAt: now(),
            idempotencyKey: $idempotencyKey,
            subjectType: 'officer_brief',
            subjectId: $fingerprint,
            metadata: [
                'title' => $title,
                'body' => $body,
                'action_url' => $canonicalUrl,
                'alliance_id' => $allianceId,
                'group' => $group,
                'state' => $state,
                'count' => $count,
                'owner' => $owner,
                'canonicalUrl' => $canonicalUrl,
                'briefFingerprint' => $fingerprint,
                'policyKey' => $policyKey,
            ],
        );
    }
}
