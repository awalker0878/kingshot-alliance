<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Integrations\Enums\WebhookDeliveryStatus;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Models\PlatformAdministrator;
use Illuminate\Support\Facades\DB;

final readonly class ProductionLaunchReadiness
{
    public function __construct(private RuntimeConfigurationValidator $configuration) {}

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
        $unprotectedAdministratorCount = PlatformAdministrator::query()
            ->whereNull('revoked_at')
            ->whereHas('user', static fn ($query) => $query
                ->whereNull('email_verified_at')
                ->orWhereNull('two_factor_confirmed_at'))
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
        ];
    }
}
