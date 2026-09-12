<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeWorkspaceOperationalHealth;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use stdClass;

final readonly class PlatformAdministrationQuery
{
    public function __construct(private GiftCodeWorkspaceOperationalHealth $giftCodeWorkspaceHealth, private PlatformCatalogueQuery $catalogues) {}

    /** @param array<string,?string> $cursors
     * @return array<string,mixed>
     */
    public function dashboard(int $actorId, ?string $correlation = null, array $cursors = []): array
    {
        $pages = [];
        $pagination = [];
        foreach (PlatformCatalogueKind::cases() as $kind) {
            if ($kind === PlatformCatalogueKind::Features) {
                continue;
            }
            $page = $this->catalogues->page($actorId, $kind, $cursors[$kind->value] ?? null, correlation: $correlation);
            $pages[$kind->value] = $page['items'];
            unset($page['items']);
            $pagination[$kind->value] = $page;
        }
        $outboxGraceMinutes = max(1, (int) config('operations.launch.outbox_grace_minutes', 15));
        $maximumOutboxAttempts = max(1, (int) config('operations.outbox.maximum_attempts', 10));
        $plans = DB::table('platform_plans')->where('is_active', true)->orderBy('code')->get(['code', 'name']);
        $entitlements = DB::table('platform_plan_entitlements')->whereIn('plan_code', $plans->pluck('code'))->orderBy('entitlement_key')->get(['plan_code', 'entitlement_key', 'limit_value'])->groupBy('plan_code');

        return [
            'metrics' => [
                'activeAdministrators' => DB::table('platform_administrators')->whereNull('revoked_at')->count(),
                'alliances' => Alliance::query()->count(),
                'activeAlliances' => Alliance::query()->where('status', AllianceStatus::Active->value)->count(),
                'suspendedAlliances' => Alliance::query()->where('status', AllianceStatus::Suspended->value)->count(),
                'closedAlliances' => Alliance::query()->where('status', AllianceStatus::Closed->value)->count(),
                'deletedAlliances' => Alliance::query()->where('status', AllianceStatus::Deleted->value)->count(),
                'pendingOutbox' => OutboxMessage::query()->whereNull('published_at')->count(),
                'overdueOutbox' => OutboxMessage::query()
                    ->whereNull('published_at')
                    ->where('available_at', '<=', now()->subMinutes($outboxGraceMinutes))
                    ->count(),
                'exhaustedOutbox' => OutboxMessage::query()
                    ->whereNull('published_at')
                    ->where('attempts', '>=', $maximumOutboxAttempts)
                    ->count(),
                'pendingWebhooks' => WebhookDelivery::query()->whereIn('status', ['pending', 'queued', 'delivering'])->count(),
                'failedWebhooks' => WebhookDelivery::query()->where('status', 'failed')->count(),
                'failedJobs' => DB::table('failed_jobs')->count(),
                'defaultQueue' => Queue::size('default'),
                'notificationsQueue' => Queue::size('notifications'),
                'integrationsQueue' => Queue::size('integrations'),
                'maintenanceQueue' => Queue::size('maintenance'),
                'failedNotifications' => NotificationDelivery::query()
                    ->where('status', DeliveryStatus::Failed->value)
                    ->count(),
            ],
            'alliances' => $pages['alliances'],
            'administrators' => $pages['administrators'],
            'legalHolds' => $pages['legalHolds'],
            'pagination' => $pagination,
            'plans' => $plans->map(static fn (stdClass $plan): array => [
                'code' => (string) $plan->code, 'name' => (string) $plan->name,
                'entitlements' => $entitlements->get($plan->code, collect())->pluck('limit_value', 'entitlement_key')->map(static fn ($value): int => (int) $value)->all(),
            ])->all(),
            'diagnostics' => [
                'outboxGraceMinutes' => $outboxGraceMinutes,
                'maximumOutboxAttempts' => $maximumOutboxAttempts,
                'giftCodeWorkspace' => $this->giftCodeWorkspaceHealth->snapshot(),
                'outboxFailures' => $pages['outboxFailures'],
                'webhookFailures' => $pages['webhookFailures'],
                'notificationFailures' => $pages['notificationFailures'],
                'failedJobs' => $pages['failedJobs'],
                'correlation' => $correlation,
                'correlatedAudit' => $pages['correlatedAudit'],
            ],
        ];
    }
}
