<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Support;

use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use Illuminate\Support\Facades\Queue;

final class IntegrationCatalogueFixture
{
    /** @return array{credential:ApiCredential,webhook:WebhookSubscription,delivery:WebhookDelivery} */
    public static function seed(string $allianceId, string $actorId): array
    {
        Queue::fake();
        $issued = app(CreateApiCredential::class)->handle($allianceId, $actorId, 'History 000', ['alliance:read']);
        $credential = ApiCredential::query()->findOrFail($issued->credentialId);
        $issuedWebhook = app(CreateWebhookSubscription::class)->handle($allianceId, $actorId,
            'History 000', 'https://hooks.example.test/events', ['event.created']);
        $webhook = WebhookSubscription::query()->findOrFail($issuedWebhook->subscriptionId);
        $id = app(QueueWebhookTestDelivery::class)->handle($allianceId, $actorId, (string) $webhook->id);
        $delivery = WebhookDelivery::query()->findOrFail($id);
        $delivery->forceFill(['status' => 'failed', 'last_error' => 'History 000'])->save();
        for ($i = 1; $i < 61; $i++) {
            $name = sprintf('History %03d', $i);
            $copy = $credential->replicate();
            $copy->forceFill(['name' => $name, 'prefix' => 'hist-'.substr($actorId, -14).'-'.$i,
                'revoked_at' => $i === 60 ? null : now(), 'expires_at' => $i === 60 ? now()->subDay() : null])->save();
            $subscription = $webhook->replicate();
            $subscription->forceFill(['name' => $name, 'is_active' => false, 'revoked_at' => now()])->save();
            $attempt = $delivery->replicate();
            $attempt->forceFill(['idempotency_key' => 'history-'.$actorId.'-'.$i, 'last_error' => $name])->save();
        }

        return ['credential' => $credential, 'webhook' => $webhook, 'delivery' => $delivery];
    }
}
