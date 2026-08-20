<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Database\Eloquent\Builder;

final readonly class QueueGiftCodeExpiryNotifications
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private NotificationDeliveryService $deliveries,
    ) {}

    public function handle(int $limit = 100): int
    {
        $redemptions = GiftCodeRedemption::query()
            ->whereNotIn('status', [
                GiftCodeRedemptionStatus::Redeemed->value,
                GiftCodeRedemptionStatus::AlreadyRedeemed->value,
                GiftCodeRedemptionStatus::Expired->value,
                GiftCodeRedemptionStatus::InvalidCode->value,
            ])
            ->whereHas('giftCode', static fn (Builder $query): Builder => $query
                ->whereIn('status', [
                    GiftCodeStatus::Pending->value,
                    GiftCodeStatus::Valid->value,
                    GiftCodeStatus::Disputed->value,
                ])
                ->whereBetween('expires_at', [now(), now()->addDay()]))
            ->with('giftCode')
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->get();
        $players = $this->players->byIds($redemptions
            ->map(static fn (GiftCodeRedemption $redemption): string => $redemption->player_id)
            ->all());
        $queued = 0;

        foreach ($redemptions as $redemption) {
            $player = $players[$redemption->player_id] ?? null;
            $giftCode = $redemption->giftCode;
            if ($player === null || $player->userId === null || $giftCode->expires_at === null) {
                continue;
            }

            $batch = $this->deliveries->queueEnabledChannelBatch(
                notificationType: 'gift_code.expiring',
                recipientUserId: $player->userId,
                playerId: $player->playerId,
                dueAt: now(),
                idempotencyKey: implode(':', [
                    'gift-code-expiring',
                    (string) $giftCode->id,
                    $player->playerId,
                    $giftCode->expires_at->toIso8601String(),
                ]),
                subjectType: 'gift_code',
                subjectId: (string) $giftCode->id,
                metadata: [
                    'title' => 'Gift Code expires soon',
                    'body' => sprintf(
                        '%s expires at %s. Finish redemption at the official center.',
                        $giftCode->code,
                        $giftCode->expires_at->toIso8601String(),
                    ),
                    'action_url' => '/gift-codes',
                ],
            );
            if ($batch->hasCreatedDeliveries()) {
                $queued++;
            }
        }

        return $queued;
    }
}
