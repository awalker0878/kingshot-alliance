<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class QueueDueGiftCodeReminders
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private NotificationDeliveryService $deliveries,
    ) {}

    public function handle(int $limit = 100): int
    {
        if (! (bool) config('game_world.gift_codes.notification_fanout', false)) {
            return 0;
        }
        $states = GiftCodeAccountState::query()
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->with('giftCode')
            ->orderBy('remind_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->get();
        $queued = 0;

        foreach ($states as $state) {
            $giftCode = $state->giftCode;
            $ownedPlayerIds = $this->players->ownedIds($state->user_id);
            if ($ownedPlayerIds !== [] && $giftCode->status === GiftCodeStatus::Valid && ! $giftCode->expires_at?->isPast()) {
                $urgency = $giftCode->expires_at?->isBefore(now()->addDay())
                    ? NotificationUrgency::High
                    : NotificationUrgency::Normal;
                $receipt = $this->deliveries->queue(new NotificationIntent(
                    notificationType: 'gift_code.reminder',
                    recipientUserId: $state->user_id,
                    playerId: null,
                    availableAt: CarbonImmutable::now('UTC'),
                    idempotencyKey: implode(':', [
                        'gift-code-reminder',
                        (string) $giftCode->id,
                        (string) $state->user_id,
                        (string) $giftCode->status_revision,
                        (string) $giftCode->expires_revision,
                        $state->remind_at?->format('YmdHi') ?? 'due',
                    ]),
                    title: 'Gift Code reminder',
                    body: sprintf('%s is ready to redeem for your eligible Governors.', $giftCode->code),
                    actionUrl: '/gift-codes/workspace?view=ready',
                    subjectType: 'gift_code',
                    subjectId: (string) $giftCode->id,
                    urgency: $urgency,
                    metadata: [
                        'gift_code_id' => (string) $giftCode->id,
                        'status_revision' => $giftCode->status_revision,
                        'expires_revision' => $giftCode->expires_revision,
                    ],
                    eligiblePlayerIds: $ownedPlayerIds,
                ));
                if ($receipt->count() > 0) {
                    ++$queued;
                }
            }

            DB::transaction(function () use ($state): void {
                $locked = GiftCodeAccountState::query()->whereKey($state->id)->lockForUpdate()->first();
                if ($locked instanceof GiftCodeAccountState && $locked->remind_at?->isPast()) {
                    $locked->remind_at = null;
                    $locked->last_action_at = CarbonImmutable::now('UTC');
                    $locked->save();
                }
            });
        }

        return $queued;
    }
}
