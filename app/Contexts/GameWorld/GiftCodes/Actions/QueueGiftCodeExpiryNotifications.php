<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeNotificationSweep;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final readonly class QueueGiftCodeExpiryNotifications
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private NotificationDeliveryService $deliveries,
    ) {}

    public function handle(int $limit = 100, ?string $afterRedemptionId = null): GiftCodeNotificationSweep
    {
        $startedAt = hrtime(true);
        $limit = max(1, min($limit, 500));
        if (! (bool) config('game_world.gift_codes.notification_fanout', false)) {
            return $this->result($startedAt);
        }

        $rows = GiftCodeRedemption::query()
            ->when($afterRedemptionId !== null && $afterRedemptionId !== '', static fn (Builder $query) => $query->where('id', '>', $afterRedemptionId))
            ->whereNotIn('status', [GiftCodeRedemptionStatus::Redeemed->value, GiftCodeRedemptionStatus::AlreadyRedeemed->value, GiftCodeRedemptionStatus::Expired->value, GiftCodeRedemptionStatus::InvalidCode->value])
            ->whereHas('giftCode', static fn (Builder $query): Builder => $query
                ->whereIn('status', [GiftCodeStatus::Pending->value, GiftCodeStatus::Valid->value])
                ->where('expires_revision', '>', 0)
                ->whereBetween('expires_at', [now(), now()->addDay()]))
            ->with('giftCode')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();
        $truncated = $rows->count() > $limit;
        $redemptions = $rows->take($limit)->values();
        $players = $this->players->byIds($redemptions->map(static fn (GiftCodeRedemption $redemption): string => $redemption->player_id)->all());
        $eligible = 0;
        $deliveries = 0;
        $created = 0;
        $skipped = 0;

        foreach ($redemptions as $redemption) {
            $player = $players[$redemption->player_id] ?? null;
            $giftCode = $redemption->giftCode;
            if ($player === null || $player->userId === null || $giftCode->expires_at === null || $giftCode->expires_revision < 1
                || ! in_array($giftCode->status, [GiftCodeStatus::Pending, GiftCodeStatus::Valid], true)
                || in_array($redemption->status, [GiftCodeRedemptionStatus::Redeemed, GiftCodeRedemptionStatus::AlreadyRedeemed, GiftCodeRedemptionStatus::Expired, GiftCodeRedemptionStatus::InvalidCode], true)) {
                $skipped++;
                continue;
            }
            $eligible++;

            $receipt = $this->deliveries->queue(new NotificationIntent(
                notificationType: 'gift_code.expiring',
                recipientUserId: $player->userId,
                playerId: $player->playerId,
                availableAt: CarbonImmutable::now('UTC'),
                idempotencyKey: implode(':', ['gift-code-expiring', (string) $giftCode->id, $player->playerId, (string) $giftCode->expires_revision, (string) $giftCode->status_revision]),
                title: 'Gift Code expires soon',
                body: sprintf('%s expires at %s. Finish redemption at the official center.', $giftCode->code, $giftCode->expires_at->toIso8601String()),
                actionUrl: '/gift-codes/'.$giftCode->id,
                subjectType: 'gift_code',
                subjectId: (string) $giftCode->id,
                urgency: NotificationUrgency::High,
                metadata: [
                    'gift_code_id' => (string) $giftCode->id,
                    'status_revision' => $giftCode->status_revision,
                    'expires_revision' => $giftCode->expires_revision,
                ],
            ));
            $deliveries += $receipt->count();
            $created += count($receipt->createdDeliveryIds);
            if ($receipt->count() === 0) {
                $skipped++;
            }
        }

        $last = $redemptions->last();
        $nextCursor = $truncated && $last instanceof GiftCodeRedemption ? (string) $last->id : null;
        $result = $this->result($startedAt, $redemptions->count(), $eligible, $deliveries, $created, $skipped, $nextCursor, $truncated);
        Log::info('gift_codes.expiry_notification_sweep', $result->toArray());
        return $result;
    }

    private function result(int $startedAt, int $examined = 0, int $eligible = 0, int $deliveryCount = 0, int $created = 0, int $skipped = 0, ?string $nextCursor = null, bool $truncated = false): GiftCodeNotificationSweep
    {
        return new GiftCodeNotificationSweep($examined, $eligible, $deliveryCount, $created, $skipped, $nextCursor, $truncated, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
