<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\AllianceAdministration\Services\PlanEntitlementService;
use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use App\Contexts\Platform\Integrations\ValueObjects\IssuedWebhookSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateWebhookSubscription
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private PlanEntitlementService $entitlements,
        private WebhookEndpointPolicy $endpointPolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $events */
    public function handle(string $allianceId, string $actorPlayerId, string $name, string $url, array $events): IssuedWebhookSubscription
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Webhook subscription name is required.']);
        }

        $events = array_values(array_unique(array_map('strval', $events)));
        if ($events === [] || count($events) > 20) {
            throw ValidationException::withMessages(['events' => 'Choose between 1 and 20 webhook event types.']);
        }
        foreach ($events as $event) {
            if (! WebhookEventCatalog::isValidSelector($event)) {
                throw ValidationException::withMessages(['events' => 'Choose only supported public webhook event types or wildcard (*).']);
            }
        }
        if (in_array('*', $events, true)) {
            $events = ['*'];
        }

        $this->endpointPolicy->assertAllowed($url);

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $name, $url, $events): IssuedWebhookSubscription {
            [$currentAlliance, $currentActor] = $this->allianceAuthority->authorizeManagerExclusive($actorPlayerId, $allianceId);

            $settings = AlliancePlatformSetting::query()->lockForUpdate()->find($currentAlliance->allianceId);
            if ($settings !== null && ! $settings->webhooks_enabled) {
                throw ValidationException::withMessages(['webhooks' => 'Webhooks are disabled for this alliance.']);
            }

            $this->entitlements->assertWebhookCapacity((string) $currentAlliance->allianceId);

            $subscription = WebhookSubscription::query()->create([
                'alliance_id' => $currentAlliance->allianceId,
                'name' => $name,
                'url' => $url,
                'events' => $events,
                'signing_secret' => bin2hex(random_bytes(32)),
                'is_active' => true,
                'created_by_player_id' => $currentActor->playerId,
            ]);

            $this->audit->record('integration.webhook.created', $currentActor, $subscription, $currentAlliance->allianceId, [
                'subscription_id' => $subscription->id,
                'url_host' => parse_url($url, PHP_URL_HOST),
                'events' => $events,
            ]);
            $this->outbox->record('integration.webhook.created', $currentAlliance->allianceId, $subscription, [
                'subscription_id' => $subscription->id,
                'events' => $events,
            ]);

            return new IssuedWebhookSubscription(
                subscriptionId: (string) $subscription->id,
                name: (string) $subscription->name,
                signingSecret: (string) $subscription->signing_secret,
            );
        });
    }
}
