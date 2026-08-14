<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Integrations\Models\WebhookSubscription;
use App\Domain\Integrations\Services\WebhookEndpointPolicy;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Platform\Services\PlanEntitlementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateWebhookSubscription
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private PlanEntitlementService $entitlements,
        private WebhookEndpointPolicy $endpointPolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $events */
    public function handle(Alliance $alliance, Player $actor, string $name, string $url, array $events): WebhookSubscription
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::AllianceManage)) {
            throw new AuthorizationException;
        }

        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Webhook subscription name is required.']);
        }

        $events = array_values(array_unique(array_map('strval', $events)));
        if ($events === [] || count($events) > 20) {
            throw ValidationException::withMessages(['events' => 'Choose between 1 and 20 webhook event types.']);
        }
        foreach ($events as $event) {
            if ($event !== '*' && preg_match('/^[a-z0-9._-]{3,120}$/', $event) !== 1) {
                throw ValidationException::withMessages(['events' => 'Webhook event names contain an unsupported value.']);
            }
        }

        $this->endpointPolicy->assertAllowed($url);

        return DB::transaction(function () use ($alliance, $actor, $name, $url, $events): WebhookSubscription {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $lockedActor = Player::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $this->authorization->allowsForUpdate($lockedActor, $currentAlliance, PermissionKey::AllianceManage)) {
                throw new AuthorizationException;
            }

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
                'created_by_player_id' => $lockedActor->id,
            ]);

            $this->audit->record('integration.webhook.created', $lockedActor, $subscription, $currentAlliance, [
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
