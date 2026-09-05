<?php

declare(strict_types=1);

namespace App\ReadModels\ProductionLaunch;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Shared\Infrastructure\Runtime\Services\RuntimeConfigurationValidator;
use Illuminate\Support\Facades\DB;
use OpenSSLAsymmetricKey;

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

        $schedulerSource = file_get_contents(base_path('routes/console.php'));
        $notificationScheduleReady = is_string($schedulerSource)
            && str_contains($schedulerSource, "Schedule::command('notifications:deliver --limit=100')->everyMinute()")
            && str_contains($schedulerSource, "Schedule::command('notifications:build-digests --limit=500')->everyMinute()")
            && str_contains($schedulerSource, "Schedule::command('notifications:deliver-digests --limit=100')->everyMinute()");
        $enabledWebPushEndpoints = NotificationEndpoint::query()
            ->where('channel', DeliveryChannel::WebPush->value)
            ->where('enabled', true)
            ->count();
        $webPushReady = $enabledWebPushEndpoints === 0 || $this->webPushConfigurationReady();
        $enabledEmailPreferences = NotificationPreference::query()
            ->where('channel', DeliveryChannel::Email->value)
            ->where('enabled', true)
            ->count();
        $mailReady = $enabledEmailPreferences === 0 || $this->mailConfigurationReady();

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
            [
                'key' => 'notification_delivery_schedule',
                'passed' => $notificationScheduleReady,
                'detail' => $notificationScheduleReady
                    ? 'Immediate delivery, digest construction, and digest delivery are scheduled every minute with overlap protection.'
                    : 'Notification immediate/digest scheduler wiring is incomplete.',
            ],
            [
                'key' => 'notification_web_push_configuration',
                'passed' => $webPushReady,
                'detail' => $enabledWebPushEndpoints === 0
                    ? 'No enabled Web Push endpoint requires VAPID configuration.'
                    : ($webPushReady
                        ? sprintf('%d enabled Web Push endpoint(s) have usable VAPID configuration.', $enabledWebPushEndpoints)
                        : sprintf('%d enabled Web Push endpoint(s) require valid VAPID public/private keys and subject.', $enabledWebPushEndpoints)),
            ],
            [
                'key' => 'notification_email_configuration',
                'passed' => $mailReady,
                'detail' => $enabledEmailPreferences === 0
                    ? 'No enabled email notification preference requires a production mail transport.'
                    : ($mailReady
                        ? sprintf('%d enabled email notification preference(s) have a production mail transport and sender.', $enabledEmailPreferences)
                        : sprintf('%d enabled email notification preference(s) require a non-log/non-array mail transport and valid sender.', $enabledEmailPreferences)),
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

    private function webPushConfigurationReady(): bool
    {
        $publicKey = trim((string) config('services.webpush.public_key', ''));
        $privateKey = trim((string) config('services.webpush.private_key', ''));
        $subject = trim((string) config('services.webpush.subject', ''));
        $decodedPublicKey = $this->decodeBase64Url($publicKey);
        if ($decodedPublicKey === null
            || strlen($decodedPublicKey) !== 65
            || ord($decodedPublicKey[0]) !== 4
            || $privateKey === ''
            || ! $this->validVapidSubject($subject)) {
            return false;
        }

        $pem = str_contains($privateKey, 'BEGIN PRIVATE KEY') || str_contains($privateKey, 'BEGIN EC PRIVATE KEY')
            ? str_replace('\\n', "\n", $privateKey)
            : base64_decode($privateKey, true);
        if (! is_string($pem) || trim($pem) === '') {
            return false;
        }

        return openssl_pkey_get_private($pem) instanceof OpenSSLAsymmetricKey;
    }

    private function validVapidSubject(string $subject): bool
    {
        if (str_starts_with($subject, 'mailto:')) {
            return filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL) !== false;
        }

        return filter_var($subject, FILTER_VALIDATE_URL) !== false
            && mb_strtolower((string) parse_url($subject, PHP_URL_SCHEME)) === 'https';
    }

    private function mailConfigurationReady(): bool
    {
        $mailer = trim((string) config('mail.default', ''));
        $configuration = $mailer === '' ? null : config('mail.mailers.'.$mailer);
        $from = trim((string) config('mail.from.address', ''));

        return $mailer !== ''
            && ! in_array($mailer, ['array', 'log'], true)
            && is_array($configuration)
            && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function decodeBase64Url(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            return null;
        }

        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
