<?php

declare(strict_types=1);

namespace App\Domain\Platform\Queries;

use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Platform\Models\LegalHold;
use App\Domain\Platform\Models\PlatformAdministrator;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class PlatformAdministrationQuery
{
    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $alliances = Alliance::query()
            ->select('alliances.*')
            ->selectSub(
                DB::table('alliance_memberships')
                    ->selectRaw('count(*)')
                    ->whereColumn('alliance_memberships.alliance_id', 'alliances.id')
                    ->where('alliance_memberships.status', 'active'),
                'active_members_count',
            )
            ->selectSub(
                DB::table('media_assets')
                    ->selectRaw('coalesce(sum(size_bytes), 0)')
                    ->whereColumn('media_assets.alliance_id', 'alliances.id'),
                'storage_bytes',
            )
            ->selectSub(
                DB::table('api_credentials')
                    ->selectRaw('count(*)')
                    ->whereColumn('api_credentials.alliance_id', 'alliances.id')
                    ->whereNull('api_credentials.revoked_at'),
                'active_api_credentials',
            )
            ->selectSub(
                DB::table('webhook_subscriptions')
                    ->selectRaw('count(*)')
                    ->whereColumn('webhook_subscriptions.alliance_id', 'alliances.id')
                    ->where('webhook_subscriptions.is_active', true)
                    ->whereNull('webhook_subscriptions.revoked_at'),
                'active_webhooks',
            )
            ->selectSub(
                DB::table('outbox_messages')
                    ->selectRaw('count(*)')
                    ->whereColumn('outbox_messages.alliance_id', 'alliances.id')
                    ->whereNull('outbox_messages.published_at'),
                'pending_outbox',
            )
            ->orderBy('name')
            ->limit(200)
            ->get();

        $planAssignments = DB::table('alliance_plan_assignments')
            ->pluck('plan_code', 'alliance_id');
        $settings = DB::table('alliance_platform_settings')
            ->get()
            ->keyBy('alliance_id');

        return [
            'metrics' => [
                'alliances' => Alliance::query()->count(),
                'activeAlliances' => Alliance::query()->where('status', AllianceStatus::Active->value)->count(),
                'suspendedAlliances' => Alliance::query()->where('status', AllianceStatus::Suspended->value)->count(),
                'closedAlliances' => Alliance::query()->where('status', AllianceStatus::Closed->value)->count(),
                'deletedAlliances' => Alliance::query()->where('status', AllianceStatus::Deleted->value)->count(),
                'pendingOutbox' => OutboxMessage::query()->whereNull('published_at')->count(),
                'pendingWebhooks' => WebhookDelivery::query()->whereIn('status', ['pending', 'delivering'])->count(),
                'failedWebhooks' => WebhookDelivery::query()->where('status', 'failed')->count(),
                'failedJobs' => DB::table('failed_jobs')->count(),
                'defaultQueue' => Queue::size('default'),
                'notificationsQueue' => Queue::size('notifications'),
                'integrationsQueue' => Queue::size('integrations'),
                'maintenanceQueue' => Queue::size('maintenance'),
            ],
            'alliances' => $alliances->map(static function (Alliance $alliance) use ($planAssignments, $settings): array {
                $setting = $settings->get($alliance->id);

                return [
                    'id' => (string) $alliance->id,
                    'name' => (string) $alliance->name,
                    'slug' => (string) $alliance->slug,
                    'status' => $alliance->status->value,
                    'timezone' => (string) $alliance->timezone,
                    'activeMembers' => (int) $alliance->getAttribute('active_members_count'),
                    'storageBytes' => (int) $alliance->getAttribute('storage_bytes'),
                    'apiCredentials' => (int) $alliance->getAttribute('active_api_credentials'),
                    'webhooks' => (int) $alliance->getAttribute('active_webhooks'),
                    'pendingOutbox' => (int) $alliance->getAttribute('pending_outbox'),
                    'plan' => (string) ($planAssignments->get($alliance->id) ?? 'standard'),
                    'retentionDays' => is_object($setting) ? (int) $setting->retention_days : 30,
                    'queuePartition' => is_object($setting) ? (string) $setting->queue_partition : 'standard',
                    'apiAccessEnabled' => is_object($setting) ? (bool) $setting->api_access_enabled : true,
                    'webhooksEnabled' => is_object($setting) ? (bool) $setting->webhooks_enabled : true,
                    'retentionUntil' => $alliance->retention_until?->toIso8601String(),
                    'lifecycleReason' => $alliance->lifecycle_reason,
                ];
            })->all(),
            'administrators' => PlatformAdministrator::query()
                ->with('user:id,name,email,two_factor_confirmed_at')
                ->orderByDesc('granted_at')
                ->get()
                ->map(static fn (PlatformAdministrator $administrator): array => [
                    'id' => (string) $administrator->id,
                    'userId' => (int) $administrator->user_id,
                    'name' => $administrator->user?->name,
                    'email' => $administrator->user?->email,
                    'mfaEnabled' => $administrator->user?->two_factor_confirmed_at !== null,
                    'grantedAt' => $administrator->granted_at->toIso8601String(),
                    'revokedAt' => $administrator->revoked_at?->toIso8601String(),
                ])->all(),
            'plans' => DB::table('platform_plans')
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(static fn (object $plan): array => [
                    'code' => (string) $plan->code,
                    'name' => (string) $plan->name,
                    'entitlements' => DB::table('platform_plan_entitlements')
                        ->where('plan_code', $plan->code)
                        ->orderBy('entitlement_key')
                        ->pluck('limit_value', 'entitlement_key')
                        ->map(static fn ($value): int => (int) $value)
                        ->all(),
                ])->all(),
            'legalHolds' => LegalHold::query()
                ->whereNull('released_at')
                ->latest('placed_at')
                ->limit(100)
                ->get()
                ->map(static fn (LegalHold $hold): array => [
                    'id' => (string) $hold->id,
                    'subjectType' => (string) $hold->subject_type,
                    'subjectId' => (string) $hold->subject_id,
                    'reason' => (string) $hold->reason,
                    'placedAt' => $hold->placed_at->toIso8601String(),
                ])->all(),
        ];
    }
}
