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
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $states = GiftCodeAccountState::query()
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', $now)
            ->orderBy('remind_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->get(['id', 'remind_at']);
        $queued = 0;

        foreach ($states as $snapshot) {
            $queued += DB::transaction(function () use ($snapshot, $now): int {
                $state = GiftCodeAccountState::query()->whereKey($snapshot->id)->lockForUpdate()->first();
                if (! $state instanceof GiftCodeAccountState
                    || $state->remind_at === null
                    || $snapshot->remind_at === null
                    || ! $state->remind_at->equalTo($snapshot->remind_at)
                    || $state->remind_at->isAfter($now)) {
                    return 0;
                }

                // Claim one exact occurrence. Communications only persists delivery
                // intent here; external delivery happens after this transaction.
                $queued = 0;
                $giftCode = $state->giftCode;
                $ownedPlayerIds = array_values(array_unique($this->players->ownedIds($state->user_id)));
                sort($ownedPlayerIds, SORT_STRING);
                if ($ownedPlayerIds !== [] && $giftCode->status === GiftCodeStatus::Valid && ! $giftCode->expires_at?->lessThanOrEqualTo($now)) {
                    $urgency = $giftCode->expires_at?->isBefore($now->addDay())
                        ? NotificationUrgency::High
                        : NotificationUrgency::Normal;
                    $receipt = $this->deliveries->queue(new NotificationIntent(
                        notificationType: 'gift_code.reminder',
                        recipientUserId: $state->user_id,
                        playerId: null,
                        availableAt: $now,
                        idempotencyKey: implode(':', [
                            'gift-code-reminder',
                            (string) $state->id,
                            $state->remind_at->format('YmdHis'),
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
                            'eligible_player_ids' => $ownedPlayerIds,
                        ],
                        eligiblePlayerIds: $ownedPlayerIds,
                    ));
                    if ($receipt->count() > 0) {
                        $queued = 1;
                    }
                }

                $state->remind_at = null;
                $state->last_action_at = $now;
                $state->save();

                return $queued;
            });
        }

        return $queued;
    }
}
