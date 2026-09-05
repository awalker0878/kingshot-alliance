<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Queries;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeCatalogQuery;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Queries\EventAttentionQuery;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\ReadModels\IntelligenceSignals\Queries\IntelligenceSignalQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class CommandOverviewQuery
{
    public function __construct(
        private EventCalendarQuery $calendar,
        private EventAttentionQuery $attention,
        private GiftCodeCatalogQuery $giftCodes,
        private AllianceIntelligenceAuthorization $intelligenceAuthorization,
        private TransferAuthorization $transferAuthorization,
        private EventAuthorization $eventAuthorization,
        private IntelligenceSignalQuery $intelligenceSignals,
        private AllianceCommandQuery $allianceCommand,
        private OfficerBriefQuery $officerBriefs,
    ) {}

    /**
     * @return array{
     *   unreadNotifications:int,
     *   pendingGiftCodes:int,
     *   upcomingEvents:list<array<string,mixed>>,
     *   eventActions:list<array<string,mixed>>,
     *   giftCodes:list<array<string,mixed>>,
     *   giftCodeLifecycle:array{newRedeemable:int,inProgress:int,retryDue:int,disputedRetracted:int},
     *   recruitment:?array{pending:int,overdue:int},
     *   intelligenceSignals:list<array<string,mixed>>,
     *   allianceCommand:?array<string,mixed>,
     *   officerBriefs:list<array<string,mixed>>,
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
        [$pendingGiftCodes, $giftCodes, $giftCodeLifecycle] = $this->actionableGiftCodes($player->playerId);
        $recruitment = $allianceId !== null && $canManageRecruitment
            ? $this->recruitment($allianceId)
            : null;
        $intelligenceSignals = [];
        if ($allianceId !== null
            && $this->intelligenceAuthorization->allows(
                $player->playerId,
                $allianceId,
                IntelligencePermission::View,
            )) {
            $canViewTransfer = $this->transferAuthorization->allows(
                $player->playerId,
                $allianceId,
                TransferPermission::View,
            );
            $canViewBearHunt = $this->eventAuthorization->allows(
                $player->playerId,
                EventScope::Alliance,
                $allianceId,
                OperationsPermission::EventAllianceView,
            );
            $intelligenceSignals = $this->intelligenceSignals->recentForAlliance(
                allianceId: $allianceId,
                actorPlayerId: $player->playerId,
                limit: 4,
                includeTransfer: $canViewTransfer,
                includeRecruitment: $canManageRecruitment,
                includeBearHunt: $canViewBearHunt,
            );
        }

        $allianceCommand = $allianceId === null ? null : $this->allianceCommand->for(
            $userId,
            $player,
            $allianceId,
            $intelligenceSignals,
        );

        return [
            'unreadNotifications' => $unreadNotifications,
            'pendingGiftCodes' => $pendingGiftCodes,
            'upcomingEvents' => $this->upcomingEvents($player),
            'eventActions' => $eventActions,
            'giftCodes' => $giftCodes,
            'giftCodeLifecycle' => $giftCodeLifecycle,
            'recruitment' => $recruitment,
            // Informational intelligence changes do not inflate actionCount.
            'intelligenceSignals' => $intelligenceSignals,
            'allianceCommand' => $allianceCommand,
            'officerBriefs' => $allianceId === null
                ? []
                : $this->officerBriefs->for($player, $allianceId, $allianceCommand),
            'actionCount' => $unreadNotifications
                + $pendingGiftCodes
                + count($eventActions)
                + ($recruitment['overdue'] ?? 0),
        ];
    }

    private function unreadNotifications(int $userId, string $playerId): int
    {
        return NotificationMessage::query()
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->where(static fn (Builder $query) => $query
                ->whereNull('player_id')
                ->orWhere('player_id', $playerId))
            ->whereExists(static function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('notification_deliveries')
                    ->whereColumn('notification_deliveries.notification_message_id', 'notification_messages.id')
                    ->where(static fn (QueryBuilder $delivery) => $delivery
                        ->where(static fn (QueryBuilder $inApp) => $inApp
                            ->where('channel', DeliveryChannel::InApp->value)
                            ->where('status', DeliveryStatus::Sent->value))
                        ->orWhere(static fn (QueryBuilder $failed) => $failed
                            ->where('channel', '!=', DeliveryChannel::InApp->value)
                            ->where('status', DeliveryStatus::Failed->value)));
            })
            ->count();
    }

    /** @return list<array<string, mixed>> */
    private function upcomingEvents(PlayerReference $player): array
    {
        return array_values($this->calendar->calendar($player, pastDays: 0, futureDays: 30)
            ->take(4)
            ->map(static function (EventOccurrence $occurrence): array {
                $event = $occurrence->event;

                return [
                    'id' => (string) $occurrence->id,
                    'title' => $event->title,
                    'nameKey' => (string) $event->eventType->name_key,
                    'scope' => $event->scope->value,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                ];
            })
            ->values()
            ->all());
    }

    /** @return array{0:int,1:list<array<string,mixed>>,2:array{newRedeemable:int,inProgress:int,retryDue:int,disputedRetracted:int}} */
    private function actionableGiftCodes(string $playerId): array
    {
        $pending = 0;
        $items = [];
        $lifecycle = [
            'newRedeemable' => 0,
            'inProgress' => 0,
            'retryDue' => 0,
            'disputedRetracted' => 0,
        ];
        $codes = $this->giftCodes->forPlayer($playerId, 100);

        foreach ($codes as $giftCode) {
            $redemption = $this->giftCodes->redemptionFor($giftCode, $playerId);
            if (in_array($giftCode->status->value, ['disputed', 'quarantined', 'invalid', 'expired'], true)
                && $redemption instanceof GiftCodeRedemption) {
                $lifecycle['disputedRetracted']++;
                $pending++;
                if (count($items) < 4) {
                    $items[] = [
                        'id' => (string) $giftCode->id,
                        'code' => (string) $giftCode->code,
                        'status' => 'disputed_retracted',
                        'trustStatus' => $giftCode->status->value,
                        'expiresAt' => $giftCode->expires_at?->toIso8601String(),
                    ];
                }

                continue;
            }

            if ($giftCode->status->value !== 'valid'
                || ($giftCode->expires_at !== null && $giftCode->expires_at->isPast())
                || ($redemption instanceof GiftCodeRedemption && $redemption->status->successful())) {
                continue;
            }

            $state = 'new_redeemable';
            if (! $redemption instanceof GiftCodeRedemption) {
                $lifecycle['newRedeemable']++;
            } elseif ($redemption->status === GiftCodeRedemptionStatus::AwaitingConfirmation) {
                $state = 'in_progress';
                $lifecycle['inProgress']++;
            } elseif ($redemption->status->retryable()
                && ($redemption->next_attempt_at === null || $redemption->next_attempt_at->isPast())) {
                $state = 'retry_due';
                $lifecycle['retryDue']++;
            } else {
                continue;
            }

            $pending++;
            if (count($items) >= 4) {
                continue;
            }
            $items[] = [
                'id' => (string) $giftCode->id,
                'code' => (string) $giftCode->code,
                'status' => $state,
                'trustStatus' => $giftCode->status->value,
                'expiresAt' => $giftCode->expires_at?->toIso8601String(),
            ];
        }

        return [$pending, $items, $lifecycle];
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
