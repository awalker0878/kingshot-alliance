<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use App\Contexts\Platform\Models\AlliancePlatformSetting;
use App\Contexts\Platform\Services\PlanEntitlementService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateWebhookSubscription
{
    public function __construct(
        private AllianceMutationAuthority $mutations,
        private PlanEntitlementService $entitlements,
        private WebhookEndpointPolicy $endpointPolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $events */
    public function handle(Alliance $alliance, Player $actor, string $name, string $url, array $events): WebhookSubscription
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

        $this->endpointPolicy->assertAllowed($url);

        return DB::transaction(function () use ($alliance, $actor, $name, $url, $events): WebhookSubscription {
            // Webhook capacity is Alliance-wide, so serialize this quota-sensitive mutation.
            $authority = $this->mutations->requireExclusive($actor, $alliance, AlliancePermission::Manage);
            $currentAlliance = $authority->alliance;
            $currentActor = $authority->actor;

            $settings = AlliancePlatformSetting::query()->lockForUpdate()->find($currentAlliance->id);
            if ($settings !== null && ! $settings->webhooks_enabled) {
                throw ValidationException::withMessages(['webhooks' => 'Webhooks are disabled for this alliance.']);
            }

            $this->entitlements->assertWebhookCapacity($currentAlliance);

            $subscription = WebhookSubscription::query()->create([
                'alliance_id' => $currentAlliance->id,
                'name' => $name,
                'url' => $url,
                'events' => $events,
                'signing_secret' => bin2hex(random_bytes(32)),
                'is_active' => true,
                'created_by_player_id' => $currentActor->id,
            ]);

            $this->audit->record('integration.webhook.created', $currentActor, $subscription, $currentAlliance, [
                'subscription_id' => $subscription->id,
                'url_host' => parse_url($url, PHP_URL_HOST),
                'events' => $events,
            ]);
            $this->outbox->record('integration.webhook.created', $currentAlliance->id, $subscription, [
                'subscription_id' => $subscription->id,
                'events' => $events,
            ]);

            return $subscription;
        });
    }
}
