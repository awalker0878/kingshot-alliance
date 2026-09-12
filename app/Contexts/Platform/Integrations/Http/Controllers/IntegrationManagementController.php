<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Actions\RetryWebhookDelivery;
use App\Contexts\Platform\Integrations\Actions\RevokeApiCredential;
use App\Contexts\Platform\Integrations\Actions\RevokeWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\RotateWebhookSigningSecret;
use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use App\Contexts\Platform\Integrations\Enums\IntegrationCatalogueKind;
use App\Contexts\Platform\Integrations\Queries\IntegrationManagementQuery;
use App\Contexts\Platform\Integrations\Queries\IntegrationUsageQuery;
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
        PlanEntitlementQuery $entitlements,
        IntegrationManagementQuery $catalogues,
        IntegrationUsageQuery $usage,
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

        $rules = [];
        foreach (IntegrationCatalogueKind::cases() as $kind) {
            $rules[$kind->value.'_cursor'] = ['nullable', 'string', 'max:4096'];
        }
        $validated = $request->validate($rules);
        $pages = [];
        $pagination = [];
        foreach (IntegrationCatalogueKind::cases() as $kind) {
            $pages[$kind->value] = $catalogues->page($context->scope()->playerId, $allianceId, $kind, $validated[$kind->value.'_cursor'] ?? null);
            $pagination[$kind->value] = array_diff_key($pages[$kind->value], ['items' => true]);
        }

        return Inertia::render('Alliance/Connections/Manage', [
            'user' => [
                'name' => $account->name,
                'email' => $account->email,
            ],
            'actorId' => $context->scope()->playerId,
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'settings' => [
                'apiAccessEnabled' => $apiAccessEnabled,
                'webhooksEnabled' => $webhooksEnabled,
            ],
            'limits' => $entitlements->limits($allianceId),
            'allowedScopes' => CreateApiCredential::allowedScopes(),
            'publicWebhookEvents' => WebhookEventCatalog::publicEvents(),
            'credentials' => $pages['credentials']['items'],
            'webhooks' => $pages['webhooks']['items'],
            'recentDeliveries' => $pages['deliveries']['items'],
            'pagination' => $pagination,
            'activeCounts' => ['credentials' => $usage->activeCredentials($allianceId), 'webhooks' => $usage->activeWebhooks($allianceId)],
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
