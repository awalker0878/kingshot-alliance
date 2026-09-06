<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use Carbon\CarbonImmutable;

final readonly class QueueGiftCodeSourceOperationalAlerts
{
    public const NOTIFICATION_TYPE = 'gift_code.source_alert';

    public function __construct(private NotificationDeliveryService $delivery) {}

    /** @return array{sources:int,alerts:int,recipients:int,queued:int} */
    public function handle(int $sourceLimit = 100): array
    {
        $sources = GiftCodeSourceRegistry::query()
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->limit(max(1, min(500, $sourceLimit)))
            ->get();
        $administratorIds = PlatformAdministrator::query()
            ->whereNull('revoked_at')
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        $alertCount = 0;
        $queued = 0;
        foreach ($sources as $source) {
            foreach ($this->alertsFor($source) as $alert) {
                $alertCount++;
                foreach ($administratorIds as $userId) {
                    $receipt = $this->delivery->queue(new NotificationIntent(
                        notificationType: self::NOTIFICATION_TYPE,
                        recipientUserId: $userId,
                        playerId: null,
                        availableAt: CarbonImmutable::now('UTC'),
                        idempotencyKey: implode('|', [
                            self::NOTIFICATION_TYPE,
                            (string) $source->id,
                            $alert['code'],
                            $alert['meaning_key'],
                            (string) $userId,
                        ]),
                        title: 'Gift Code source needs attention',
                        body: sprintf('%s: %s', $source->name, $alert['message']),
                        actionUrl: '/platform/gift-codes/sources',
                        subjectType: 'gift_code_source',
                        subjectId: (string) $source->id,
                        urgency: $alert['urgency'],
                        metadata: [
                            'source_id' => (string) $source->id,
                            'source_key' => $source->source_key,
                            'alert_code' => $alert['code'],
                            'health_status' => $source->health_status,
                        ],
                    ));
                    $queued += $receipt->createdMessage ? 1 : 0;
                }
            }
        }

        return [
            'sources' => $sources->count(),
            'alerts' => $alertCount,
            'recipients' => count($administratorIds),
            'queued' => $queued,
        ];
    }

    /** @return list<array{code:string,message:string,meaning_key:string,urgency:NotificationUrgency}> */
    private function alertsFor(GiftCodeSourceRegistry $source): array
    {
        $alerts = [];
        $failureStates = [
            'authentication_failed' => ['Provider authentication failed.', NotificationUrgency::Urgent],
            'permission_revoked' => ['Provider permission was revoked.', NotificationUrgency::Urgent],
            'contract_changed' => ['Provider identity or contract no longer matches the configured source.', NotificationUrgency::High],
            'parser_failed' => ['Provider content no longer matches the supported parser contract.', NotificationUrgency::High],
        ];
        if (isset($failureStates[$source->health_status])) {
            [$message, $urgency] = $failureStates[$source->health_status];
            $alerts[] = [
                'code' => $source->health_status,
                'message' => $message,
                'meaning_key' => $source->last_ingestion_failure_at?->format('Uv') ?? (string) $source->updated_at?->format('Uv'),
                'urgency' => $urgency,
            ];
        }

        $staleMinutes = max(15, min(10_080, (int) config('game_world.gift_codes.source_stale_minutes', 90)));
        if ($source->next_eligible_ingestion_at === null
            && ($source->last_ingestion_success_at === null || $source->last_ingestion_success_at->lt(now()->subMinutes($staleMinutes)))) {
            $alerts[] = [
                'code' => 'source_stale',
                'message' => sprintf('No successful acquisition has completed within %d minutes.', $staleMinutes),
                'meaning_key' => $source->last_ingestion_success_at?->format('Uv') ?? 'never',
                'urgency' => NotificationUrgency::High,
            ];
        }

        $quarantineThreshold = max(2, min(20, (int) config('game_world.gift_codes.quarantine_alert_consecutive_runs', 3)));
        if ($source->consecutive_quarantined_runs >= $quarantineThreshold) {
            $alerts[] = [
                'code' => 'quarantine_spike',
                'message' => sprintf('%d consecutive acquisition runs contained quarantined evidence.', $source->consecutive_quarantined_runs),
                'meaning_key' => (string) $source->quarantined_observation_count,
                'urgency' => NotificationUrgency::High,
            ];
        }

        if ($source->reconciliation_gap_count > 0 && $source->last_reconciliation_gap_at !== null) {
            $alerts[] = [
                'code' => 'reconciliation_gap',
                'message' => 'Reconciliation found an official Gift Code publication that was not seen through the configured push transport.',
                'meaning_key' => $source->reconciliation_gap_count.'|'.$source->last_reconciliation_gap_at->format('Uv'),
                'urgency' => NotificationUrgency::Urgent,
            ];
        }

        $quotaThreshold = max(0, (int) config('game_world.gift_codes.quota_alert_remaining', 10));
        if ($source->last_quota_remaining !== null && $source->last_quota_remaining <= $quotaThreshold) {
            $alerts[] = [
                'code' => 'provider_quota_low',
                'message' => sprintf('Provider quota is low (%d remaining).', $source->last_quota_remaining),
                'meaning_key' => (string) $source->last_quota_remaining,
                'urgency' => NotificationUrgency::High,
            ];
        }

        foreach (GiftCodeSourceSubscription::query()
            ->where('gift_code_source_id', $source->id)
            ->whereIn('status', ['pending', 'active'])
            ->get() as $subscription) {
            if ($subscription->status === 'pending'
                && $subscription->updated_at !== null
                && $subscription->updated_at->lt(now()->subMinutes(30))) {
                $alerts[] = [
                    'code' => 'subscription_pending',
                    'message' => sprintf('%s %s subscription has remained pending for more than 30 minutes.', $subscription->provider, $subscription->transport),
                    'meaning_key' => (string) $subscription->updated_at->format('Uv'),
                    'urgency' => NotificationUrgency::High,
                ];
            }
            if ($subscription->expires_at !== null && $subscription->expires_at->lte(now()->addHours(12))) {
                $alerts[] = [
                    'code' => 'subscription_expiring',
                    'message' => sprintf('%s %s subscription is near expiry.', $subscription->provider, $subscription->transport),
                    'meaning_key' => (string) $subscription->expires_at->format('Uv'),
                    'urgency' => NotificationUrgency::High,
                ];
            }
        }

        return $alerts;
    }
}
