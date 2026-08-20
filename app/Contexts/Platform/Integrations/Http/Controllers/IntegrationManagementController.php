<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\AllianceAdministration\Services\PlanEntitlementService;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Actions\RetryWebhookDelivery;
use App\Contexts\Platform\Integrations\Actions\RevokeApiCredential;
use App\Contexts\Platform\Integrations\Actions\RevokeWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\RotateWebhookSigningSecret;
use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationManagementController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly AllianceReferenceQuery $alliances,
    ) {}

    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        PlanEntitlementService $entitlements,
    ): Response {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);
        $allianceId = $context->scope()->allianceId;
        $alliance = $this->alliances->require($allianceId);
        abort_unless($authorization->allows($context->scope()->playerId, $allianceId, AlliancePermission::Manage), 403);
        $settings = AlliancePlatformSetting::query()->whereKey($allianceId)->first();
        $apiAccessEnabled = $settings instanceof AlliancePlatformSetting
            ? (bool) $settings->api_access_enabled
            : true;
        $webhooksEnabled = $settings instanceof AlliancePlatformSetting
            ? (bool) $settings->webhooks_enabled
            : true;

        return Inertia::render('Alliance/Connections/Manage', [
            'user' => [
                'name' => $account->name,
                'email' => $account->email,
            ],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'settings' => [
                'apiAccessEnabled' => $apiAccessEnabled,
                'webhooksEnabled' => $webhooksEnabled,
            ],
            'limits' => $entitlements->limits($allianceId),
            'allowedScopes' => CreateApiCredential::allowedScopes(),
            'publicWebhookEvents' => WebhookEventCatalog::publicEvents(),
            'credentials' => ApiCredential::query()
                ->where('alliance_id', $allianceId)
                ->latest()
                ->get()
                ->map(static fn (ApiCredential $credential): array => [
                    'id' => (string) $credential->id,
                    'name' => (string) $credential->name,
                    'prefix' => (string) $credential->prefix,
                    'scopes' => $credential->scopes,
                    'expiresAt' => $credential->expires_at?->toIso8601String(),
                    'lastUsedAt' => $credential->last_used_at?->toIso8601String(),
                    'revokedAt' => $credential->revoked_at?->toIso8601String(),
                ])->all(),
            'webhooks' => WebhookSubscription::query()
                ->where('alliance_id', $allianceId)
                ->latest()
                ->get()
                ->map(static fn (WebhookSubscription $subscription): array => [
                    'id' => (string) $subscription->id,
                    'name' => (string) $subscription->name,
                    'url' => (string) $subscription->url,
                    'events' => $subscription->events,
                    'active' => (bool) $subscription->is_active,
                    'secretRotatedAt' => $subscription->secret_rotated_at?->toIso8601String(),
                    'revokedAt' => $subscription->revoked_at?->toIso8601String(),
                ])->all(),
            'recentDeliveries' => WebhookDelivery::query()
                ->where('alliance_id', $allianceId)
                ->latest()
                ->limit(50)
                ->get()
                ->map(static fn (WebhookDelivery $delivery): array => [
                    'id' => (string) $delivery->id,
                    'subscriptionId' => (string) $delivery->webhook_subscription_id,
                    'event' => (string) $delivery->event_type,
                    'status' => $delivery->status->value,
                    'attempts' => (int) $delivery->attempts,
                    'responseCode' => $delivery->response_code,
                    'lastError' => $delivery->last_error,
                    'lastAttemptAt' => $delivery->last_attempt_at?->toIso8601String(),
                    'deliveredAt' => $delivery->delivered_at?->toIso8601String(),
                ])->all(),
            'issuedCredential' => $request->session()->get('issued_api_credential'),
            'issuedWebhookSecret' => $request->session()->get('issued_webhook_secret'),
        ]);
    }

    public function createCredential(Request $request, AllianceContext $context, CreateApiCredential $create): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1', 'max:8'],
            'scopes.*' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $issued = $create->handle(
            $context->scope()->allianceId,
            $context->scope()->playerId,
            (string) $validated['name'],
            array_values(array_map('strval', $validated['scopes'])),
            isset($validated['expires_at']) ? CarbonImmutable::parse((string) $validated['expires_at'], 'UTC') : null,
        );

        return back()
            ->with('issued_api_credential', [
                'id' => $issued->credentialId,
                'name' => $issued->name,
                'token' => $issued->token,
            ])
            ->with('actionReceipt', $this->receipt('api-credential-created'));
    }

    public function revokeCredential(
        Request $request,
        AllianceContext $context,
        string $credential,
        RevokeApiCredential $revoke,
    ): RedirectResponse {
        $scope = $context->scope();
        $revoke->handle($scope->allianceId, $scope->playerId, $credential);

        return back()->with('actionReceipt', $this->receipt('api-credential-revoked'));
    }

    public function createWebhook(Request $request, AllianceContext $context, CreateWebhookSubscription $create): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*' => ['required', 'string', 'max:120'],
        ]);
        $subscription = $create->handle(
            $context->scope()->allianceId,
            $context->scope()->playerId,
            (string) $validated['name'],
            (string) $validated['url'],
            array_values(array_map('strval', $validated['events'])),
        );

        return back()
            ->with('issued_webhook_secret', [
                'id' => $subscription->subscriptionId,
                'name' => $subscription->name,
                'secret' => $subscription->signingSecret,
            ])
            ->with('actionReceipt', $this->receipt('webhook-created'));
    }

    public function revokeWebhook(
        Request $request,
        AllianceContext $context,
        string $subscription,
        RevokeWebhookSubscription $revoke,
    ): RedirectResponse {
        $scope = $context->scope();
        $revoke->handle($scope->allianceId, $scope->playerId, $subscription);

        return back()->with('actionReceipt', $this->receipt('webhook-revoked'));
    }

    public function testWebhook(
        Request $request,
        AllianceContext $context,
        string $subscription,
        QueueWebhookTestDelivery $queue,
    ): RedirectResponse {
        $scope = $context->scope();
        $queue->handle($scope->allianceId, $scope->playerId, $subscription);

        return back()->with('actionReceipt', $this->receipt('webhook-test-queued'));
    }

    public function rotateWebhookSecret(
        Request $request,
        AllianceContext $context,
        string $subscription,
        RotateWebhookSigningSecret $rotate,
    ): RedirectResponse {
        $scope = $context->scope();
        $issued = $rotate->handle($scope->allianceId, $scope->playerId, $subscription);

        return back()
            ->with('issued_webhook_secret', [
                'id' => $issued->subscriptionId,
                'name' => $issued->name,
                'secret' => $issued->signingSecret,
            ])
            ->with('actionReceipt', $this->receipt('webhook-secret-rotated'));
    }

    public function retryDelivery(
        Request $request,
        AllianceContext $context,
        string $delivery,
        RetryWebhookDelivery $retry,
    ): RedirectResponse {
        $scope = $context->scope();
        $retry->handle($scope->allianceId, $scope->playerId, $delivery);

        return back()->with('actionReceipt', $this->receipt('webhook-delivery-retried'));
    }
}
