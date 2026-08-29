<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Actions;

use App\ReadModels\CommandOverview\Queries\AllianceCommandQuery;
use App\ReadModels\CommandOverview\Queries\OfficerBriefQuery;
use App\ReadModels\CommandOverview\Services\OfficerBriefNotificationPublisher;
use App\ReadModels\NotificationDelivery\Queries\AllianceNotificationRecipientQuery;
use App\ReadModels\NotificationDelivery\ValueObjects\NotificationQueueSweep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final readonly class QueueOfficerBriefNotifications
{
    public const GROUP_ALL = 'all';

    public const GROUP_DAILY = 'daily';

    public const GROUP_EVENT = 'event';

    /** @var list<string> */
    public const GROUP_OPTIONS = [self::GROUP_ALL, self::GROUP_DAILY, self::GROUP_EVENT];

    public function __construct(
        private AllianceNotificationRecipientQuery $recipients,
        private AllianceCommandQuery $command,
        private OfficerBriefQuery $briefs,
        private OfficerBriefNotificationPublisher $publisher,
    ) {}

    public function handle(
        string $group = self::GROUP_ALL,
        int $limit = 1000,
        ?string $afterMembershipId = null,
        ?Carbon $asOf = null,
    ): NotificationQueueSweep {
        if (! in_array($group, self::GROUP_OPTIONS, true)) {
            throw new InvalidArgumentException('Choose a supported Officer Brief queue group.');
        }

        $startedAt = hrtime(true);
        $asOf ??= Carbon::now('UTC');
        $page = $this->recipients->officers($limit, $afterMembershipId);
        $authorized = 0;
        $facts = 0;
        $deliveries = 0;
        $created = 0;
        $skipped = $page->examinedCount - count($page->recipients);

        foreach ($page->recipients as $recipient) {
            $userId = $recipient->player->userId;
            if ($userId === null) {
                $skipped++;

                continue;
            }

            try {
                $command = $this->command->for(
                    $userId,
                    $recipient->player,
                    $recipient->allianceId,
                );
                if ($command === null) {
                    $skipped++;

                    continue;
                }
                $authorized++;

                foreach ($this->briefs->for($recipient->player, $recipient->allianceId, $command) as $brief) {
                    $briefGroup = (string) ($brief['group'] ?? '');
                    if (! $this->supportsGroup($group, $briefGroup)
                        || ! $this->eligible($brief, $recipient->timezone, $asOf)) {
                        continue;
                    }

                    $facts++;
                    $policy = $briefGroup === 'daily_officer'
                        ? 'daily:'.$this->localTime($asOf, $recipient->timezone)->toDateString()
                        : 'semantic';
                    $batch = $this->publisher->publish(
                        $userId,
                        $recipient->player->playerId,
                        $recipient->allianceId,
                        $brief,
                        $policy,
                    );
                    $deliveries += $batch->count();
                    $created += count($batch->createdDeliveryIds);
                }
            } catch (AuthorizationException) {
                // Authority may be revoked after recipient discovery. That
                // recipient is intentionally skipped without aborting the sweep.
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
        Log::info('notifications.officer_brief_sweep', $result->toArray());

        return $result;
    }

    private function supportsGroup(string $requested, string $briefGroup): bool
    {
        return match ($requested) {
            self::GROUP_DAILY => $briefGroup === 'daily_officer',
            self::GROUP_EVENT => in_array($briefGroup, ['upcoming_event', 'post_event_closeout'], true),
            self::GROUP_ALL => in_array($briefGroup, ['daily_officer', 'upcoming_event', 'post_event_closeout'], true),
            default => false,
        };
    }

    /** @param array<string,mixed> $brief */
    private function eligible(array $brief, string $timezone, Carbon $asOf): bool
    {
        $group = (string) ($brief['group'] ?? '');
        if ($group === 'daily_officer') {
            $local = $this->localTime($asOf, $timezone);

            return $local->hour >= max(0, min(23, (int) config(
                'notification_delivery.officer_brief_daily_local_hour',
                9,
            )));
        }

        return ($brief['state'] ?? null) !== 'not_available'
            && is_array($brief['facts'] ?? null)
            && $brief['facts'] !== [];
    }

    private function localTime(Carbon $asOf, string $timezone): Carbon
    {
        try {
            return $asOf->copy()->setTimezone($timezone);
        } catch (Throwable) {
            return $asOf->copy()->setTimezone('UTC');
        }
    }
}
