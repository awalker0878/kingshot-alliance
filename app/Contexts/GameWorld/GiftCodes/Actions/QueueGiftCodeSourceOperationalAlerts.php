<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceAlertProgress;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use App\Contexts\Platform\Administration\Services\PlatformAdministratorDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class QueueGiftCodeSourceOperationalAlerts
{
    public const NOTIFICATION_TYPE = 'gift_code.source_alert';

    public function __construct(
        private NotificationDeliveryService $delivery,
        private PlatformAdministratorDirectory $administrators,
    ) {}

    /** @return array{sources:int,alerts:int,recipients:int,queued:int} */
    public function handle(int $sourceLimit = 100): array
    {
        return DB::transaction(function () use ($sourceLimit): array {
            $summary = ['sources' => 0, 'alerts' => 0, 'recipients' => 0, 'queued' => 0];
            $state = DB::table('gift_code_source_alert_sweep')->where('id', 'scheduled')->lock('for update skip locked')->first();
            if ($state === null) {
                return $summary;
            }
            $eligible = GiftCodeSourceRegistry::query()->where('is_active', true)->where('ingestion_enabled', true)->whereNull('revoked_at');
            $through = $state->source_through_id ?? (clone $eligible)->max('id');
            if ($through === null) {
                DB::table('gift_code_source_alert_sweep')->where('id', 'scheduled')->update(['last_source_id' => null, 'source_through_id' => null, 'last_batch_at' => now()]);

                return $summary;
            }
            $after = $state->last_source_id;
            $ids = (clone $eligible)->when($after !== null, static fn ($q) => $q->where('id', '>', $after))
                ->where('id', '<=', $through)->orderBy('id')->limit(max(1, min(500, $sourceLimit)))->pluck('id');
            if ($ids->isEmpty() && $after !== null) {
                $after = null;
                $through = (clone $eligible)->max('id');
                $ids = $through === null ? collect() : (clone $eligible)->where('id', '<=', $through)->orderBy('id')->limit(max(1, min(500, $sourceLimit)))->pluck('id');
            }
            $budget = 500;
            foreach ($ids as $id) {
                // Source mutation uses the same row; no provider I/O or foreign grant locks occur here.
                $source = (clone $eligible)->whereKey($id)->lockForUpdate()->first([
                    'id', 'name', 'source_key', 'health_status', 'last_ingestion_failure_at', 'updated_at',
                    'next_eligible_ingestion_at', 'last_ingestion_success_at', 'consecutive_quarantined_runs',
                    'quarantined_observation_count', 'reconciliation_gap_count', 'last_reconciliation_gap_at', 'last_quota_remaining',
                ]);
                if (! $source instanceof GiftCodeSourceRegistry) {
                    $after = $id;

                    continue;
                }
                $progress = GiftCodeSourceAlertProgress::query()->firstOrCreate(['gift_code_source_id' => $id], ['recipient_after_id' => 0]);
                $subscriptions = GiftCodeSourceSubscription::query()->where('gift_code_source_id', $id)->whereIn('status', ['pending', 'active']);
                $progress->subscription_through_id ??= (clone $subscriptions)->max('id');
                $page = (clone $subscriptions)->when($progress->subscription_after_id !== null,
                    static fn ($q) => $q->where('id', '>', $progress->subscription_after_id))
                    ->when($progress->subscription_through_id === null, static fn ($q) => $q->whereRaw('1 = 0'),
                        static fn ($q) => $q->where('id', '<=', $progress->subscription_through_id))->orderBy('id')->limit(25)
                    ->get(['id', 'provider', 'transport', 'status', 'updated_at', 'expires_at']);
                $alerts = $this->alertsFor($source, $page);
                if (count($alerts) > $budget) {
                    break;
                }
                $progress->recipient_through_id ??= $this->administrators->lastActiveUserId();
                $recipientLimit = $alerts === [] ? 25 : min(25, intdiv($budget, count($alerts)));
                $recipients = $alerts === [] ? [] : $this->administrators->activeUserIdsAfter(
                    $progress->recipient_after_id, $progress->recipient_through_id, $recipientLimit);
                foreach ($recipients as $userId) {
                    foreach ($alerts as $alert) {
                        $receipt = $this->delivery->queue(new NotificationIntent(
                            notificationType: self::NOTIFICATION_TYPE, recipientUserId: $userId, playerId: null,
                            availableAt: CarbonImmutable::now('UTC'),
                            idempotencyKey: implode('|', [self::NOTIFICATION_TYPE, (string) $id, $alert['code'], $alert['meaning_key'], (string) $userId]),
                            title: 'Gift Code source needs attention', body: sprintf('%s: %s', $source->name, $alert['message']),
                            actionUrl: '/platform/gift-codes/sources', subjectType: 'gift_code_source', subjectId: (string) $id,
                            urgency: $alert['urgency'], metadata: ['source_id' => (string) $id, 'source_key' => $source->source_key,
                                'alert_code' => $alert['code'], 'health_status' => $source->health_status],
                        ));
                        $summary['queued'] += $receipt->createdMessage ? 1 : 0;
                        $budget--;
                    }
                    $progress->recipient_after_id = $userId;
                }
                if (count($recipients) < $recipientLimit || $progress->recipient_after_id >= $progress->recipient_through_id) {
                    // Every recipient in this frozen frontier saw this subscription page. Move one dimension at a time.
                    $progress->recipient_after_id = 0;
                    $progress->recipient_through_id = null;
                    $last = $page->last();
                    $progress->subscription_after_id = $last instanceof GiftCodeSourceSubscription ? (string) $last->id : null;
                    if ($page->count() < 25 || $progress->subscription_after_id === $progress->subscription_through_id) {
                        $progress->subscription_after_id = null;
                        $progress->subscription_through_id = null;
                    }
                }
                $progress->save();
                $summary['sources']++;
                $summary['alerts'] += count($alerts);
                $summary['recipients'] += count($recipients);
                $after = $id;
                if ($budget === 0) {
                    break;
                }
            }
            DB::table('gift_code_source_alert_sweep')->where('id', 'scheduled')->update([
                'last_source_id' => $after, 'source_through_id' => $through, 'last_batch_at' => now(),
            ]);

            return $summary;
        });
    }

    /**
     * @param  Collection<int,GiftCodeSourceSubscription>  $subscriptions
     * @return list<array{code:string,message:string,meaning_key:string,urgency:NotificationUrgency}>
     */
    private function alertsFor(GiftCodeSourceRegistry $source, Collection $subscriptions): array
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

        foreach ($subscriptions as $subscription) {
            if ($subscription->status === 'pending'
                && $subscription->updated_at !== null
                && $subscription->updated_at->lt(now()->subMinutes(30))) {
                $alerts[] = [
                    'code' => 'subscription_pending',
                    'message' => sprintf('%s %s subscription has remained pending for more than 30 minutes.', $subscription->provider, $subscription->transport),
                    'meaning_key' => $subscription->id.'|'.$subscription->updated_at->format('Uv'),
                    'urgency' => NotificationUrgency::High,
                ];
            }
            if ($subscription->expires_at !== null && $subscription->expires_at->lte(now()->addHours(12))) {
                $alerts[] = [
                    'code' => 'subscription_expiring',
                    'message' => sprintf('%s %s subscription is near expiry.', $subscription->provider, $subscription->transport),
                    'meaning_key' => $subscription->id.'|'.$subscription->expires_at->format('Uv'),
                    'urgency' => NotificationUrgency::High,
                ];
            }
        }

        return $alerts;
    }
}
