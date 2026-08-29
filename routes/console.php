<?php

declare(strict_types=1);

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Content\Actions\PublishScheduledContent;
use App\Contexts\Alliance\Content\Actions\QueuePublishedAnnouncementBroadcasts;
use App\Contexts\Alliance\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\GameWorld\GiftCodes\Actions\ExpireGiftCodes;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeExpiryNotifications;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Contributions\Actions\QueueDueContributionReports;
use App\Contexts\Intelligence\Ingestion\Actions\EnforceKingdomIngestionRetention;
use App\Contexts\Intelligence\Ingestion\Actions\QueueDueKingdomIngestionSubscriptions;
use App\Contexts\Intelligence\Ingestion\Actions\ReconcileKingdomIngestionSources;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionOperationalHealth;
use App\Contexts\Intelligence\Sharing\Actions\EnforceKingdomIntelligenceSharingRetention;
use App\Contexts\Operations\Participation\Reminders\Actions\QueueDueEventReminders;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\AllianceAdministration\Services\PlatformUsageService;
use App\Contexts\Platform\DataGovernance\Actions\EnforcePlatformRetention;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\Integrations\Actions\QueueDueWebhookDeliveries;
use App\ReadModels\CommandOverview\Actions\QueueOfficerBriefNotifications;
use App\ReadModels\IntelligenceSignals\Actions\QueueIntelligenceChangeNotifications;
use App\ReadModels\ProductionLaunch\ProductionLaunchReadiness;
use App\Shared\Infrastructure\Messaging\Outbox\Actions\PublishOutboxBatch;
use App\Shared\Infrastructure\Runtime\Services\RuntimeConfigurationValidator;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('app:config-check', function (RuntimeConfigurationValidator $validator): int {
    $errors = $validator->errors(app()->environment());
    if ($errors !== []) {
        foreach ($errors as $error) {
            $this->error($error);
        }

        return 1;
    }

    $this->info('Runtime configuration is valid.');

    return 0;
})->purpose('Validate required staging and production configuration');

Artisan::command('app:launch-check {--json}', function (ProductionLaunchReadiness $readiness): int {
    $checks = $readiness->checks();

    if ((bool) $this->option('json')) {
        $this->line(json_encode([
            'passed' => collect($checks)->every(static fn (array $check): bool => $check['passed']),
            'checks' => $checks,
        ], JSON_THROW_ON_ERROR));
    } else {
        foreach ($checks as $check) {
            $prefix = $check['passed'] ? '[PASS]' : '[FAIL]';
            $this->line(sprintf('%s %s: %s', $prefix, $check['key'], $check['detail']));
        }
    }

    return $readiness->passed() ? 0 : 1;
})->purpose('Validate repository-controlled production launch prerequisites and operational health');

Artisan::command('platform:admin:grant {email}', function (ManagePlatformAdministrator $manage): int {
    $emailArgument = $this->argument('email');
    if (! is_string($emailArgument)) {
        $this->error('A valid email argument is required.');

        return 1;
    }

    $email = Str::lower(trim($emailArgument));
    $user = User::query()->where('email', $email)->first();
    if (! $user instanceof User) {
        $this->error('No user exists with that email address.');

        return 1;
    }

    $manage->grant((int) $user->id);
    $this->info('Platform administrator grant created. Web access still requires verified email and MFA.');

    return 0;
})->purpose('Bootstrap a platform administrator grant');

Artisan::command('platform:capture-usage {--limit=500}', function (PlatformUsageService $usage): int {
    $captured = $usage->captureAll(max(1, min(2000, (int) $this->option('limit'))));
    $this->info(sprintf('Captured usage for %d alliance(s).', $captured));

    return 0;
})->purpose('Capture alliance usage and capacity snapshots');

Artisan::command('platform:process-account-deletions {--limit=100}', function (ProcessAccountDeletionRequests $process): int {
    $processed = $process->handle(max(1, min(500, (int) $this->option('limit'))));
    $this->info(sprintf('Processed %d account deletion request(s).', $processed));

    return 0;
})->purpose('Process eligible account deletion requests with legal-hold and ownership checks');

Artisan::command('platform:enforce-retention', function (EnforcePlatformRetention $retention): int {
    $result = $retention->handle();
    $this->info(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Enforce configured retention windows for operational records');

Artisan::command('integrations:queue-webhooks {--limit=100}', function (QueueDueWebhookDeliveries $queue): int {
    $queued = $queue->handle(max(1, min(500, (int) $this->option('limit'))));
    $this->info(sprintf('Queued %d due webhook delivery job(s).', $queued));

    return 0;
})->purpose('Recover and queue due webhook deliveries');

Artisan::command('kingdoms:bootstrap-admin {kingdom} {player}', function (BootstrapKingdomAdministrator $bootstrap): int {
    $kingdomInput = $this->argument('kingdom');
    if (! is_string($kingdomInput)) {
        $this->error('Kingdom must be an existing positive numeric Kingdom number.');

        return 1;
    }

    $kingdomArgument = trim($kingdomInput);
    if ($kingdomArgument === '' || ! ctype_digit($kingdomArgument)) {
        $this->error('Kingdom must be an existing positive numeric Kingdom number.');

        return 1;
    }

    $kingdom = Kingdom::query()->where('number', (int) $kingdomArgument)->first();
    if (! $kingdom instanceof Kingdom) {
        $this->error('No Kingdom exists with that number.');

        return 1;
    }

    $playerInput = $this->argument('player');
    if (! is_string($playerInput)) {
        $this->error('No Player exists with that ID.');

        return 1;
    }

    $player = Player::query()->find(trim($playerInput));
    if (! $player instanceof Player) {
        $this->error('No Player exists with that ID.');

        return 1;
    }

    $assignment = $bootstrap->handle((string) $kingdom->id, (string) $player->id);
    $this->info(sprintf(
        'Bootstrapped Kingdom #%d administrator to Player %s.',
        $assignment->kingdomNumber,
        $assignment->playerId,
    ));

    return 0;
})->purpose('Bootstrap the first Kingdom administrator to a Player without granting game authority to a Platform User');

Artisan::command('kingdoms:queue-ingestion {--limit=100}', function (QueueDueKingdomIngestionSubscriptions $queue): int {
    $queued = $queue->handle(max(1, min(500, (int) $this->option('limit'))));
    $this->info(sprintf('Queued %d due Kingdom ingestion subscription(s).', $queued));

    return 0;
})->purpose('Queue due approved Kingdom ingestion subscriptions');

Artisan::command('kingdoms:reconcile-ingestion-sources {--limit=500}', function (ReconcileKingdomIngestionSources $reconcile): int {
    $revoked = $reconcile->handle(max(1, min(2000, (int) $this->option('limit'))));
    $this->info(sprintf('Disabled %d Kingdom ingestion subscription(s) with revoked source approval.', $revoked));

    return 0;
})->purpose('Disable Kingdom ingestion subscriptions whose approved source/version was revoked');

Artisan::command('kingdoms:enforce-ingestion-retention', function (EnforceKingdomIngestionRetention $retention): int {
    $this->info(json_encode($retention->handle(), JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Enforce KINGDOMS-004 operational retention without deleting canonical promoted history');

Artisan::command('kingdoms:enforce-sharing-retention {--limit=500}', function (EnforceKingdomIntelligenceSharingRetention $retention): int {
    $limit = max(1, min(2000, (int) $this->option('limit')));
    $this->info(json_encode($retention->handle($limit), JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Enforce bounded KINGDOMS-005 consent/grant retention without deleting canonical observations');

Artisan::command('kingdoms:ingestion-health {--json}', function (KingdomIngestionOperationalHealth $health): int {
    $snapshot = $health->snapshot();

    if ((bool) $this->option('json')) {
        $this->line(json_encode($snapshot, JSON_THROW_ON_ERROR));
    } else {
        foreach ($snapshot as $key => $value) {
            $this->line(sprintf('%s=%s', $key, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
        }
    }

    return $snapshot['attentionRequired'] ? 1 : 0;
})->purpose('Report bounded KINGDOMS-004 operational health signals for monitoring');

Artisan::command('content:publish-scheduled {--limit=100}', function (PublishScheduledContent $publisher): int {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $published = $publisher->handle($limit);
    $this->info(sprintf('Published %d scheduled content item(s).', $published));

    return 0;
})->purpose('Publish due scheduled alliance content');

Artisan::command('content:queue-announcement-broadcasts {--limit=25}', function (QueuePublishedAnnouncementBroadcasts $queue): int {
    $limit = max(1, min(100, (int) $this->option('limit')));
    $broadcasts = $queue->handle($limit);
    $this->info(sprintf('Queued %d Alliance announcement broadcast(s).', $broadcasts));

    return 0;
})->purpose('Fan out published Alliance announcements to active members');

Artisan::command('notifications:deliver {--limit=100}', function (ProcessNotificationDeliveries $deliveries): int {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $processed = $deliveries->handle($limit);
    $this->info(sprintf('Processed %d external notification delivery attempt(s).', $processed));

    return 0;
})->purpose('Deliver due Discord and Telegram notifications with bounded retries');

Artisan::command(
    'notifications:queue-officer-briefs {--group=all} {--limit=1000} {--after=} {--cycle}',
    function (QueueOfficerBriefNotifications $queue): int {
        $group = trim((string) $this->option('group'));
        if (! in_array($group, QueueOfficerBriefNotifications::GROUP_OPTIONS, true)) {
            $this->error('Choose --group=all, --group=daily or --group=event.');

            return 1;
        }
        $afterOption = trim((string) $this->option('after'));
        $cursorKey = 'notification-delivery:officer-brief:'.$group;
        $cycle = (bool) $this->option('cycle') && $afterOption === '';
        $storedCursor = $cycle ? Cache::get($cursorKey) : null;
        $after = $afterOption !== ''
            ? $afterOption
            : (is_string($storedCursor) && $storedCursor !== '' ? $storedCursor : null);
        $result = $queue->handle(
            group: $group,
            limit: max(1, min(2000, (int) $this->option('limit'))),
            afterMembershipId: $after,
        );
        if ($cycle) {
            if ($result->nextCursor === null) {
                Cache::forget($cursorKey);
            } else {
                Cache::forever($cursorKey, $result->nextCursor);
            }
        }
        $this->line(json_encode($result->toArray(), JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('Queue bounded, authorized Daily or Event Officer Brief deliveries');

Artisan::command(
    'notifications:queue-intelligence-changes {--limit=1000} {--after=} {--cycle}',
    function (QueueIntelligenceChangeNotifications $queue): int {
        $afterOption = trim((string) $this->option('after'));
        $cursorKey = 'notification-delivery:intelligence-change';
        $cycle = (bool) $this->option('cycle') && $afterOption === '';
        $storedCursor = $cycle ? Cache::get($cursorKey) : null;
        $after = $afterOption !== ''
            ? $afterOption
            : (is_string($storedCursor) && $storedCursor !== '' ? $storedCursor : null);
        $result = $queue->handle(
            limit: max(1, min(2000, (int) $this->option('limit'))),
            afterMembershipId: $after,
        );
        if ($cycle) {
            if ($result->nextCursor === null) {
                Cache::forget($cursorKey);
            } else {
                Cache::forever($cursorKey, $result->nextCursor);
            }
        }
        $this->line(json_encode($result->toArray(), JSON_THROW_ON_ERROR));

        return 0;
    },
)->purpose('Queue bounded, authorized Intelligence change deliveries');

Artisan::command('gift-codes:maintain {--limit=100}', function (
    ExpireGiftCodes $expire,
    QueueGiftCodeExpiryNotifications $notifications,
): int {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $expired = $expire->handle($limit);
    $queued = $notifications->handle($limit);
    $this->info(sprintf('Expired %d Gift Code(s) and queued %d expiry reminder(s).', $expired, $queued));

    return 0;
})->purpose('Reconcile Gift Code expiry and queue idempotent reminders');

Artisan::command('events:queue-reminders {--limit=100}', function (QueueDueEventReminders $queue): int {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $queued = $queue->handle($limit);
    $this->info(sprintf('Queued %d due Event reminder(s).', $queued));

    return 0;
})->purpose('Materialize and queue due Event reminders');

Artisan::command('contributions:queue-reports {--limit=50}', function (QueueDueContributionReports $queue): int {
    $limit = max(1, min(250, (int) $this->option('limit')));
    $queued = $queue->handle($limit);
    $this->info(sprintf('Queued %d due contribution report(s).', $queued));

    return 0;
})->purpose('Queue due contribution reports through the notification outbox');

Artisan::command('recruitment:purge-expired {--limit=100}', function (PurgeExpiredRecruitmentCandidates $purge): int {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $anonymized = $purge->handle($limit);
    $this->info(sprintf('Anonymized %d expired recruitment candidate record(s).', $anonymized));

    return 0;
})->purpose('Anonymize unsuccessful recruitment candidates whose retention period has expired');

Artisan::command('outbox:publish {--limit=100}', function (PublishOutboxBatch $publisher): int {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $published = $publisher->handle($limit);
    $this->info(sprintf('Published %d outbox message(s).', $published));

    return 0;
})->purpose('Publish eligible transactional outbox messages');

Schedule::command('content:publish-scheduled --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('content:queue-announcement-broadcasts --limit=25')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('events:queue-reminders --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:queue-officer-briefs --group=daily --limit=1000 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:queue-officer-briefs --group=event --limit=1000 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:queue-intelligence-changes --limit=1000 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:deliver --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('gift-codes:maintain --limit=500')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('contributions:queue-reports --limit=50')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('outbox:publish --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('integrations:queue-webhooks --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('kingdoms:queue-ingestion --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('kingdoms:reconcile-ingestion-sources --limit=1000')->everyFiveMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('kingdoms:enforce-ingestion-retention')->dailyAt('04:15')->onOneServer()->withoutOverlapping(60);
Schedule::command('kingdoms:enforce-sharing-retention --limit=500')->dailyAt('04:30')->onOneServer()->withoutOverlapping(60);
Schedule::command('platform:process-account-deletions --limit=100')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('platform:capture-usage --limit=2000')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('platform:enforce-retention')->dailyAt('03:45')->onOneServer()->withoutOverlapping(60);
Schedule::command('recruitment:purge-expired --limit=250')->dailyAt('03:15')->onOneServer()->withoutOverlapping(30);
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
