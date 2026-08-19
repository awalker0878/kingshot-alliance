<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Queries;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeCatalogQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Queries\EventAttentionQuery;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use Illuminate\Database\Eloquent\Builder;

final readonly class CommandOverviewQuery
{
    public function __construct(
        private EventCalendarQuery $calendar,
        private EventAttentionQuery $attention,
        private GiftCodeCatalogQuery $giftCodes,
    ) {}

    /**
     * @return array{
     *   unreadNotifications:int,
     *   pendingGiftCodes:int,
     *   upcomingEvents:list<array<string,mixed>>,
     *   eventActions:list<array<string,mixed>>,
     *   giftCodes:list<array<string,mixed>>,
     *   recruitment:?array{pending:int,overdue:int},
     *   actionCount:int
     * }
     */
    public function for(
        int $userId,
        PlayerReference $player,
        ?string $allianceId,
        bool $canManageRecruitment,
    ): array {
        $unreadNotifications = $this->unreadNotifications($userId, $player->playerId);
        $eventActions = array_slice($this->attention->for($player), 0, 4);
        [$pendingGiftCodes, $giftCodes] = $this->actionableGiftCodes($player->playerId);
        $recruitment = $allianceId !== null && $canManageRecruitment
            ? $this->recruitment($allianceId)
            : null;

        return [
            'unreadNotifications' => $unreadNotifications,
            'pendingGiftCodes' => $pendingGiftCodes,
            'upcomingEvents' => $this->upcomingEvents($player),
            'eventActions' => $eventActions,
            'giftCodes' => $giftCodes,
            'recruitment' => $recruitment,
            'actionCount' => $unreadNotifications
                + $pendingGiftCodes
                + count($eventActions)
                + ($recruitment['overdue'] ?? 0),
        ];
    }

    private function unreadNotifications(int $userId, string $playerId): int
    {
        return NotificationDelivery::query()
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->where(static fn (Builder $query) => $query
                ->whereNull('player_id')
                ->orWhere('player_id', $playerId))
            ->where(static fn (Builder $query) => $query
                ->where(static fn (Builder $inApp) => $inApp
                    ->where('channel', DeliveryChannel::InApp->value)
                    ->where('status', DeliveryStatus::Sent->value))
                ->orWhere(static fn (Builder $failed) => $failed
                    ->where('channel', '!=', DeliveryChannel::InApp->value)
                    ->where('status', DeliveryStatus::Failed->value)))
            ->count();
    }

    /** @return list<array<string, mixed>> */
    private function upcomingEvents(PlayerReference $player): array
    {
        return $this->calendar->calendar($player, pastDays: 0, futureDays: 30)
            ->take(4)
            ->map(static function (EventOccurrence $occurrence): array {
                $event = $occurrence->event;

                return [
                    'id' => (string) $occurrence->id,
                    'title' => $event instanceof Event ? $event->title : null,
                    'nameKey' => $event instanceof Event ? (string) $event->eventType->name_key : 'events.calendar.title',
                    'scope' => $event instanceof Event ? $event->scope->value : 'player',
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{0:int,1:list<array<string,mixed>>} */
    private function actionableGiftCodes(string $playerId): array
    {
        $pending = 0;
        $items = [];
        $codes = $this->giftCodes->forPlayer($playerId, 100);

        foreach ($codes as $giftCode) {
            if ($giftCode->status !== GiftCodeStatus::Active
                || ($giftCode->expires_at !== null && $giftCode->expires_at->isPast())) {
                continue;
            }

            $redemption = $this->giftCodes->redemptionFor($giftCode, $playerId);
            if ($redemption instanceof GiftCodeRedemption && $redemption->status->successful()) {
                continue;
            }

            $pending++;
            if (count($items) >= 4) {
                continue;
            }
            $items[] = [
                'id' => (string) $giftCode->id,
                'code' => (string) $giftCode->code,
                'status' => $redemption?->status->value ?? 'not_started',
                'expiresAt' => $giftCode->expires_at?->toIso8601String(),
            ];
        }

        return [$pending, $items];
    }

    /** @return array{pending:int,overdue:int} */
    private function recruitment(string $allianceId): array
    {
        $activeStages = [
            RecruitmentStage::New->value,
            RecruitmentStage::Screening->value,
            RecruitmentStage::Contacted->value,
            RecruitmentStage::Interview->value,
            RecruitmentStage::Accepted->value,
        ];
        $query = RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('merged_into_id')
            ->whereNull('anonymized_at')
            ->whereIn('stage', $activeStages);

        return [
            'pending' => (clone $query)->count(),
            'overdue' => (clone $query)
                ->whereNotNull('next_action_at')
                ->where('next_action_at', '<=', now())
                ->count(),
        ];
    }
}
