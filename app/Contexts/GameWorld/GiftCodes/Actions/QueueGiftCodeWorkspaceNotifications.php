<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeWorkspaceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class QueueGiftCodeWorkspaceNotifications
{
    private const CURSOR_KEY = 'gift-codes:workspace-notifications:user-cursor';

    public function __construct(
        private PlayerReferenceQuery $players,
        private GiftCodeWorkspaceQuery $workspace,
        private NotificationDeliveryService $deliveries,
    ) {}

    /** @return array{examined:int,queued:int,nextCursor:?int} */
    public function cycle(int $limit = 100): array
    {
        $cursor = Cache::get(self::CURSOR_KEY);
        $afterUserId = is_int($cursor) ? $cursor : (is_numeric($cursor) ? (int) $cursor : null);
        $result = $this->handle($limit, $afterUserId);
        if ($result['nextCursor'] === null) {
            Cache::forget(self::CURSOR_KEY);
        } else {
            Cache::forever(self::CURSOR_KEY, $result['nextCursor']);
        }

        return $result;
    }

    /** @return array{examined:int,queued:int,nextCursor:?int} */
    public function handle(int $limit = 100, ?int $afterUserId = null): array
    {
        if (! (bool) config('game_world.gift_codes.notification_fanout', false)
            || ! (bool) config('game_world.gift_codes.redemption_workspace', false)) {
            return ['examined' => 0, 'queued' => 0, 'nextCursor' => null];
        }

        $limit = max(1, min(500, $limit));
        $userIds = $this->players->ownerUserIdsAfter($afterUserId, $limit + 1);
        $truncated = count($userIds) > $limit;
        $userIds = array_slice($userIds, 0, $limit);
        $queued = 0;

        foreach ($userIds as $userId) {
            $playerIds = $this->players->ownedIds($userId);
            if ($playerIds === []) {
                continue;
            }
            $page = $this->workspace->pageForAccount(
                $userId,
                $playerIds,
                GiftCodeWorkspaceQuery::VIEW_READY,
                20,
            );
            $codes = array_values(array_filter($page->items(), static fn (mixed $item): bool => $item instanceof GiftCode));
            if ($codes === []) {
                continue;
            }
            $fingerprintParts = array_map(
                static fn (GiftCode $code): string => implode('-', [(string) $code->id, (string) $code->status_revision, (string) $code->expires_revision]),
                $codes,
            );
            sort($fingerprintParts);
            $fingerprint = hash('sha256', implode('|', $fingerprintParts));
            $expiring = count(array_filter(
                $codes,
                static fn (GiftCode $code): bool => $code->expires_at?->isBefore(now()->addDay()) ?? false,
            ));
            $receipt = $this->deliveries->queue(new NotificationIntent(
                notificationType: 'gift_code.redemption_ready',
                recipientUserId: $userId,
                playerId: null,
                availableAt: CarbonImmutable::now('UTC'),
                idempotencyKey: 'gift-code-workspace:'.$userId.':'.$fingerprint,
                title: sprintf('%d Gift Codes ready to redeem', count($codes)),
                body: $expiring > 0
                    ? sprintf('%d shown codes expire within 24 hours. Start a redemption run.', $expiring)
                    : 'Start a redemption run for your eligible Governors.',
                actionUrl: '/gift-codes/workspace?view=ready',
                subjectType: 'gift_code_workspace',
                subjectId: (string) $userId,
                urgency: $expiring > 0 ? NotificationUrgency::High : NotificationUrgency::Normal,
                metadata: [
                    'shown_code_count' => count($codes),
                    'expiring_count' => $expiring,
                    'set_fingerprint' => $fingerprint,
                ],
                eligiblePlayerIds: $playerIds,
            ));
            if ($receipt->count() > 0) {
                ++$queued;
            }
        }

        return [
            'examined' => count($userIds),
            'queued' => $queued,
            'nextCursor' => $truncated && $userIds !== [] ? end($userIds) : null,
        ];
    }
}
