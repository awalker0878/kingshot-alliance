<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeNotificationCampaign;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeNotificationSweep;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final readonly class QueueGiftCodeTransitionNotifications
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AccountIdentityQuery $accounts,
        private NotificationDeliveryService $deliveries,
    ) {}

    public function handle(int $limit = 200): GiftCodeNotificationSweep
    {
        $startedAt = hrtime(true);
        $limit = max(1, min(1000, $limit));
        if (! (bool) config('game_world.gift_codes.notification_fanout', false)) {
            return $this->result($startedAt);
        }

        $campaign = GiftCodeNotificationCampaign::query()->whereNull('completed_at')->with('giftCode')->orderBy('id')->first();
        if (! $campaign instanceof GiftCodeNotificationCampaign || $campaign->giftCode === null) {
            return $this->result($startedAt);
        }
        $giftCode = $campaign->giftCode;
        $currentRevision = $campaign->status_revision === $giftCode->status_revision && $campaign->expires_revision === $giftCode->expires_revision;
        $currentlyEligible = match ($campaign->notification_type) {
            ScheduleGiftCodeNotificationCampaign::AVAILABLE => $giftCode->status === GiftCodeStatus::Valid && ($giftCode->expires_at === null || $giftCode->expires_at->isFuture()),
            ScheduleGiftCodeNotificationCampaign::TRUST_CHANGED => true,
            default => false,
        };
        if (! $currentRevision || ! $currentlyEligible) {
            $campaign->forceFill(['completed_at' => now()])->save();

            return $this->result($startedAt, skipped: 1);
        }

        $after = $campaign->cursor_user_id;
        $candidateIds = $campaign->notification_type === ScheduleGiftCodeNotificationCampaign::AVAILABLE
            ? $this->players->ownerUserIdsAfter($after, $limit + 1)
            : $this->players->ownerUserIdsWhoStartedGiftCodeAfter((string) $giftCode->id, $after, $limit + 1);
        $truncated = count($candidateIds) > $limit;
        $userIds = array_slice($candidateIds, 0, $limit);
        $accounts = $this->accounts->byIds($userIds);
        $eligible = 0;
        $deliveryCount = 0;
        $created = 0;
        $skipped = $userIds === [] ? 1 : 0;

        foreach ($userIds as $userId) {
            $account = $accounts[$userId] ?? null;
            if ($account === null || $account->anonymized) {
                $skipped++;

                continue;
            }
            $governors = $this->eligibleGovernors($userId, (string) $giftCode->id, $campaign->notification_type);
            if ($governors === []) {
                $skipped++;

                continue;
            }
            $eligible++;
            $governorNames = array_map(static fn (PlayerReference $player): string => $player->currentName, $governors);
            $title = $campaign->notification_type === ScheduleGiftCodeNotificationCampaign::AVAILABLE ? 'Gift Code available' : 'Gift Code trust changed';
            $body = $campaign->notification_type === ScheduleGiftCodeNotificationCampaign::AVAILABLE
                ? sprintf('%s is available for %s.', $giftCode->code, implode(', ', $governorNames))
                : sprintf('%s is now %s. Review started Governor redemptions.', $giftCode->code, $giftCode->status->value);

            $receipt = $this->deliveries->queue(new NotificationIntent(
                notificationType: $campaign->notification_type,
                recipientUserId: $userId,
                playerId: null,
                availableAt: CarbonImmutable::now('UTC'),
                idempotencyKey: implode(':', ['gift-code-notification', $campaign->notification_type, (string) $giftCode->id, (string) $userId, (string) $campaign->status_revision, (string) $campaign->expires_revision]),
                title: $title,
                body: $body,
                actionUrl: '/gift-codes/'.$giftCode->id,
                subjectType: 'gift_code',
                subjectId: (string) $giftCode->id,
                urgency: $campaign->notification_type === ScheduleGiftCodeNotificationCampaign::TRUST_CHANGED ? NotificationUrgency::High : NotificationUrgency::Normal,
                metadata: [
                    'gift_code_id' => (string) $giftCode->id,
                    'status' => $giftCode->status->value,
                    'status_revision' => $campaign->status_revision,
                    'expires_revision' => $campaign->expires_revision,
                    'governors' => array_map(static fn (PlayerReference $player): array => ['id' => $player->playerId, 'name' => $player->currentName, 'kingdom' => $player->kingdomNumber], $governors),
                ],
                eligiblePlayerIds: array_map(static fn (PlayerReference $player): string => $player->playerId, $governors),
            ));
            $deliveryCount += $receipt->count();
            $created += count($receipt->createdDeliveryIds);
            if ($receipt->count() === 0) {
                $skipped++;
            }
        }

        $lastUserId = $userIds === [] ? null : $userIds[array_key_last($userIds)];
        $campaign->forceFill([
            'cursor_user_id' => $truncated ? $lastUserId : null,
            'examined_count' => $campaign->examined_count + count($userIds),
            'delivery_count' => $campaign->delivery_count + $deliveryCount,
            'created_delivery_count' => $campaign->created_delivery_count + $created,
            'completed_at' => $truncated ? null : now(),
        ])->save();
        $result = $this->result($startedAt, count($userIds), $eligible, $deliveryCount, $created, $skipped, $truncated ? (string) $lastUserId : null, $truncated);
        Log::info('gift_codes.transition_notification_sweep', [...$result->toArray(), 'campaign_id' => (string) $campaign->id, 'notification_type' => $campaign->notification_type]);

        return $result;
    }

    /** @return list<PlayerReference> */
    private function eligibleGovernors(int $userId, string $giftCodeId, string $notificationType): array
    {
        $governors = $this->players->ownedByUserUpTo($userId, max(1, min(50, (int) config('game_world.gift_codes.max_governors_per_account', 50))));
        if ($governors === []) {
            return [];
        }
        $redemptions = GiftCodeRedemption::query()->where('gift_code_id', $giftCodeId)->whereIn('player_id', array_map(static fn (PlayerReference $player): string => $player->playerId, $governors))->get()->keyBy('player_id');

        return array_values(array_filter($governors, static function (PlayerReference $player) use ($redemptions, $notificationType): bool {
            $redemption = $redemptions->get($player->playerId);
            if ($notificationType === ScheduleGiftCodeNotificationCampaign::TRUST_CHANGED) {
                return $redemption instanceof GiftCodeRedemption;
            }

            return $player->gamePlayerId !== null && (! $redemption instanceof GiftCodeRedemption || ! in_array($redemption->status, [GiftCodeRedemptionStatus::Redeemed, GiftCodeRedemptionStatus::AlreadyRedeemed], true));
        }));
    }

    private function result(int $startedAt, int $examined = 0, int $eligible = 0, int $deliveryCount = 0, int $created = 0, int $skipped = 0, ?string $nextCursor = null, bool $truncated = false): GiftCodeNotificationSweep
    {
        return new GiftCodeNotificationSweep($examined, $eligible, $deliveryCount, $created, $skipped, $nextCursor, $truncated, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
