<?php

declare(strict_types=1);

namespace Tests\ReadModels\PlatformAdministration\Support;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\ReadModels\PlatformAdministration\PlatformCatalogueKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Historical catalogue fixtures, without executing live delivery or privileged commands. */
final class PlatformCatalogueFixture
{
    public const TRACE = '0123456789abcdef0123456789abcdef';

    public const PRIVATE_ERROR = 'private diagnostic content';

    /** @return list<string> Public row identifiers, newest first. */
    public static function seed(PlatformCatalogueKind $kind, int $actorId, AllianceReference $alliance, string $playerId, int $count = 61): array
    {
        $subscription = $kind === PlatformCatalogueKind::WebhookFailures ? WebhookSubscription::query()->create([
            'alliance_id' => $alliance->allianceId, 'created_by_player_id' => $playerId,
            'name' => 'Platform history', 'url' => 'https://hooks.example.test/events', 'events' => ['event.created'], 'signing_secret' => 'fixture secret',
        ]) : null;
        $messageId = (string) Str::ulid();
        if ($kind === PlatformCatalogueKind::NotificationFailures) {
            DB::table('notification_messages')->insert(['id' => $messageId, 'notification_type' => 'event.reminder',
                'recipient_user_id' => $actorId, 'title' => 'Private notification', 'body' => self::PRIVATE_ERROR,
                'available_at' => now(), 'idempotency_key' => $messageId, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = (string) Str::ulid();
            $uuid = (string) Str::uuid();
            $name = sprintf('Platform history %03d', $i);
            $timestamps = ['created_at' => now(), 'updated_at' => now()];
            [$table, $attributes] = match ($kind) {
                PlatformCatalogueKind::Alliances => ['alliances', ['id' => $id, 'kingdom_id' => $alliance->kingdomId,
                    'name' => $name, 'slug' => 'catalogue-'.strtolower($id), ...$timestamps]],
                PlatformCatalogueKind::Administrators => ['platform_administrators', ['id' => $id,
                    'user_id' => User::factory()->create(['name' => $name])->id, 'granted_by_user_id' => $actorId,
                    'granted_at' => now(), 'revoked_at' => now(), ...$timestamps]],
                PlatformCatalogueKind::LegalHolds => ['legal_holds', ['id' => $id, 'subject_type' => 'alliance',
                    'subject_id' => $alliance->allianceId, 'reason' => $name, 'placed_by_user_id' => $actorId, 'placed_at' => now(), ...$timestamps]],
                PlatformCatalogueKind::OutboxFailures => ['outbox_messages', ['id' => $id, 'alliance_id' => $alliance->allianceId,
                    'event_type' => $name, 'aggregate_type' => 'Alliance', 'aggregate_id' => $alliance->allianceId,
                    'idempotency_key' => $id, 'payload' => json_encode(['private' => self::PRIVATE_ERROR], JSON_THROW_ON_ERROR),
                    'attempts' => 10, 'last_error' => self::PRIVATE_ERROR, 'occurred_at' => now(), 'available_at' => now(), ...$timestamps]],
                PlatformCatalogueKind::WebhookFailures => ['webhook_deliveries', ['id' => $id, 'alliance_id' => $alliance->allianceId,
                    'webhook_subscription_id' => $subscription?->id, 'source_message_id' => $id, 'event_type' => $name,
                    'status' => 'failed', 'attempts' => 5, 'available_at' => now(), 'idempotency_key' => $id,
                    'last_error' => null, 'response_excerpt' => self::PRIVATE_ERROR, 'payload' => '{"private":true}', ...$timestamps]],
                PlatformCatalogueKind::NotificationFailures => ['notification_deliveries', ['id' => $id, 'notification_message_id' => $messageId,
                    'channel' => 'discord', 'due_at' => now(), 'status' => 'failed', 'attempt_count' => 5,
                    'idempotency_key' => $id, 'last_error' => self::PRIVATE_ERROR, 'failed_at' => now(), ...$timestamps]],
                PlatformCatalogueKind::FailedJobs => ['failed_jobs', ['uuid' => $uuid, 'connection' => 'redis', 'queue' => $name,
                    'payload' => '{"private":true}', 'exception' => self::PRIVATE_ERROR, 'failed_at' => now()]],
                PlatformCatalogueKind::CorrelatedAudit => ['audit_events', ['id' => $id, 'alliance_id' => $alliance->allianceId,
                    'event' => $name, 'trace_id' => self::TRACE, 'metadata' => '{"private":true}', 'created_at' => now()]],
                PlatformCatalogueKind::Features => ['alliance_feature_flags', ['id' => $id, 'alliance_id' => $alliance->allianceId,
                    'feature_key' => $name, 'enabled' => true, 'configuration' => '{"private":true}', ...$timestamps]],
            };
            DB::table($table)->insert($attributes);
            $ids[] = match ($kind) {
                PlatformCatalogueKind::Features => $name,
                PlatformCatalogueKind::FailedJobs => $uuid,
                default => $id,
            };
        }

        return array_reverse($ids);
    }
}
