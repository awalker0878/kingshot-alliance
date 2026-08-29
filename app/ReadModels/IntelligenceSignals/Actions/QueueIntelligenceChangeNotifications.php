<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\ReadModels\IntelligenceSignals\Queries\IntelligenceSignalQuery;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalNotificationPublisher;
use App\ReadModels\NotificationDelivery\Queries\AllianceNotificationRecipientQuery;
use App\ReadModels\NotificationDelivery\ValueObjects\NotificationQueueSweep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final readonly class QueueIntelligenceChangeNotifications
{
    public function __construct(
        private AllianceNotificationRecipientQuery $recipients,
        private AllianceIntelligenceAuthorization $intelligenceAuthorization,
        private AllianceAuthorization $allianceAuthorization,
        private TransferAuthorization $transferAuthorization,
        private EventAuthorization $eventAuthorization,
        private IntelligenceSignalQuery $signals,
        private IntelligenceSignalNotificationPublisher $publisher,
    ) {}

    public function handle(
        int $limit = 1000,
        ?string $afterMembershipId = null,
        ?Carbon $asOf = null,
    ): NotificationQueueSweep {
        $startedAt = hrtime(true);
        $asOf ??= Carbon::now('UTC');
        $page = $this->recipients->intelligenceMembers($limit, $afterMembershipId);
        $authorized = 0;
        $facts = 0;
        $deliveries = 0;
        $created = 0;
        $skipped = $page->examinedCount - count($page->recipients);
        $signalLimit = max(1, min(20, (int) config('notification_delivery.intelligence_signal_limit', 8)));

        foreach ($page->recipients as $recipient) {
            $userId = $recipient->player->userId;
            if ($userId === null || ! $this->intelligenceAuthorization->allows(
                $recipient->player->playerId,
                $recipient->allianceId,
                IntelligencePermission::View,
            )) {
                $skipped++;

                continue;
            }

            try {
                $authorized++;
                $signals = $this->signals->recentForAlliance(
                    allianceId: $recipient->allianceId,
                    actorPlayerId: $recipient->player->playerId,
                    limit: $signalLimit,
                    asOf: $asOf,
                    includeTransfer: $this->transferAuthorization->allows(
                        $recipient->player->playerId,
                        $recipient->allianceId,
                        TransferPermission::View,
                    ),
                    includeRecruitment: $this->allianceAuthorization->allows(
                        $recipient->player->playerId,
                        $recipient->allianceId,
                        AlliancePermission::RecruitmentManage,
                    ),
                    includeBearHunt: $this->eventAuthorization->allows(
                        $recipient->player->playerId,
                        EventScope::Alliance,
                        $recipient->allianceId,
                        OperationsPermission::EventAllianceView,
                    ),
                );

                foreach ($signals as $signal) {
                    $facts++;
                    $batch = $this->publisher->publish(
                        $userId,
                        $recipient->player->playerId,
                        $recipient->allianceId,
                        $signal,
                        'semantic',
                    );
                    $deliveries += $batch->count();
                    $created += count($batch->createdDeliveryIds);
                }
            } catch (AuthorizationException) {
                $authorized = max(0, $authorized - 1);
                $skipped++;
            }
        }

        $result = new NotificationQueueSweep(
            examinedRecipients: $page->examinedCount,
            authorizedRecipients: $authorized,
            factCount: $facts,
            deliveryCount: $deliveries,
            createdDeliveryCount: $created,
            replayedDeliveryCount: max(0, $deliveries - $created),
            skippedRecipients: $skipped,
            nextCursor: $page->nextCursor,
            truncated: $page->truncated,
            durationMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
        );
        Log::info('notifications.intelligence_change_sweep', $result->toArray());

        return $result;
    }
}
