<?php

declare(strict_types=1);

namespace App\ReadModels\ProductionLaunch;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Shared\Infrastructure\Runtime\Services\RuntimeConfigurationValidator;
use Illuminate\Support\Facades\DB;

final readonly class ProductionLaunchReadiness
{
    public function __construct(
        private RuntimeConfigurationValidator $configuration,
        private GiftCodeSourceAdapterRegistry $giftCodeSourceAdapters,
    ) {}

    /**
     * @return list<array{key: string, passed: bool, detail: string}>
     */
    public function checks(): array
    {
        $minimumAdministrators = max(2, (int) config('operations.launch.minimum_platform_administrators', 2));
        $outboxGraceMinutes = max(1, (int) config('operations.launch.outbox_grace_minutes', 15));
        $maximumOverdueOutbox = max(0, (int) config('operations.launch.maximum_overdue_outbox', 0));
        $maximumFailedJobs = max(0, (int) config('operations.launch.maximum_failed_jobs', 0));
        $webhookFailureWindowMinutes = max(1, (int) config('operations.launch.webhook_failure_window_minutes', 60));
        $maximumWebhookFailures = max(0, (int) config('operations.launch.maximum_recent_webhook_failures', 25));

        $configurationErrors = $this->configuration->errors('production');
        $administratorCount = PlatformAdministrator::query()->whereNull('revoked_at')->count();
        $unprotectedAdministratorCount = DB::table('platform_administrators as administrators')
            ->join('users', 'users.id', '=', 'administrators.user_id')
            ->whereNull('administrators.revoked_at')
            ->where(static fn ($query) => $query
                ->whereNull('users.email_verified_at')
                ->orWhereNull('users.two_factor_confirmed_at'))
            ->count();
        $alliancesMissingSettings = Alliance::query()
            ->where('status', AllianceStatus::Active->value)
            ->whereNotIn('id', AlliancePlatformSetting::query()->select('alliance_id'))
            ->count();
        $overdueOutbox = OutboxMessage::query()
            ->whereNull('published_at')
            ->where('available_at', '<=', now()->subMinutes($outboxGraceMinutes))
            ->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $recentWebhookFailures = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Failed->value)
            ->where('updated_at', '>=', now()->subMinutes($webhookFailureWindowMinutes))
            ->count();
        $giftCodeFlags = [
            'moderation' => (bool) config('game_world.gift_codes.moderation', false),
            'approved_source_ingestion' => (bool) config('game_world.gift_codes.approved_source_ingestion', false),
            'notification_fanout' => (bool) config('game_world.gift_codes.notification_fanout', false),
        ];
        $disabledGiftCodeFlags = array_keys(array_filter(
            $giftCodeFlags,
            static fn (bool $enabled): bool => ! $enabled,
        ));
        $enabledGiftCodeSources = GiftCodeSourceRegistry::query()
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->whereNull('revoked_at')
            ->get(['source_key', 'adapter_key']);
        $unavailableGiftCodeSources = $enabledGiftCodeSources
            ->filter(fn (GiftCodeSourceRegistry $source): bool => $this->giftCodeSourceAdapters->find($source->adapter_key) === null)
            ->pluck('source_key')
            ->map(static fn ($key): string => (string) $key)
            ->values()
            ->all();
        $giftCodeIngestionReady = ! $giftCodeFlags['approved_source_ingestion']
            || ($enabledGiftCodeSources->isNotEmpty() && $unavailableGiftCodeSources === []);

        return [
            [
                'key' => 'runtime_configuration',
                'passed' => $configurationErrors === [],
                'detail' => $configurationErrors === []
                    ? 'Production runtime configuration satisfies hosted security and durability requirements.'
                    : implode(' ', $configurationErrors),
            ],
            [
                'key' => 'platform_administrator_redundancy',
                'passed' => $administratorCount >= $minimumAdministrators,
                'detail' => sprintf(
                    '%d active platform administrator(s); minimum required is %d.',
                    $administratorCount,
                    $minimumAdministrators,
                ),
            ],
            [
                'key' => 'platform_administrator_mfa',
                'passed' => $unprotectedAdministratorCount === 0,
                'detail' => sprintf('%d active platform administrator(s) lack verified email or confirmed MFA.', $unprotectedAdministratorCount),
            ],
            [
                'key' => 'alliance_platform_defaults',
                'passed' => $alliancesMissingSettings === 0,
                'detail' => sprintf('%d active alliance(s) are missing platform settings.', $alliancesMissingSettings),
            ],
            [
                'key' => 'transactional_outbox_backlog',
                'passed' => $overdueOutbox <= $maximumOverdueOutbox,
                'detail' => sprintf(
                    '%d unpublished outbox message(s) are older than %d minute(s); maximum allowed is %d.',
                    $overdueOutbox,
                    $outboxGraceMinutes,
                    $maximumOverdueOutbox,
                ),
            ],
            [
                'key' => 'failed_jobs',
                'passed' => $failedJobs <= $maximumFailedJobs,
                'detail' => sprintf('%d failed queue job(s); maximum allowed is %d.', $failedJobs, $maximumFailedJobs),
            ],
            [
                'key' => 'webhook_delivery_health',
                'passed' => $recentWebhookFailures <= $maximumWebhookFailures,
                'detail' => sprintf(
                    '%d webhook delivery failure(s) occurred in the last %d minute(s); maximum allowed is %d.',
                    $recentWebhookFailures,
                    $webhookFailureWindowMinutes,
                    $maximumWebhookFailures,
                ),
            ],
            [
                'key' => 'gift_code_feature_flags',
                'passed' => $disabledGiftCodeFlags === [],
                'detail' => $disabledGiftCodeFlags === []
                    ? 'Gift Code moderation, approved-source ingestion, and notification fan-out are enabled.'
                    : 'Disabled Gift Code launch flag(s): '.implode(', ', $disabledGiftCodeFlags).'.',
            ],
            [
                'key' => 'gift_code_ingestion_sources',
                'passed' => $giftCodeIngestionReady,
                'detail' => match (true) {
                    ! $giftCodeFlags['approved_source_ingestion'] => 'Approved-source ingestion is disabled.',
                    $enabledGiftCodeSources->isEmpty() => 'No active Gift Code source is enabled for scheduled ingestion.',
                    $unavailableGiftCodeSources !== [] => 'Enabled Gift Code source(s) lack an installed adapter: '.implode(', ', $unavailableGiftCodeSources).'.',
                    default => sprintf('%d active Gift Code source(s) have installed ingestion adapters.', $enabledGiftCodeSources->count()),
                },
            ],
        ];
    }

    public function passed(): bool
    {
        foreach ($this->checks() as $check) {
            if (! $check['passed']) {
                return false;
            }
        }

        return true;
    }
}
