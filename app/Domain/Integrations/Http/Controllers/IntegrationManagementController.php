<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Actions\CreateApiCredential;
use App\Domain\Integrations\Actions\CreateWebhookSubscription;
use App\Domain\Integrations\Actions\RevokeApiCredential;
use App\Domain\Integrations\Actions\RevokeWebhookSubscription;
use App\Domain\Integrations\Models\ApiCredential;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Domain\Integrations\Models\WebhookSubscription;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\PlanEntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IntegrationManagementController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        PlanEntitlementService $entitlements,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $alliance = $context->alliance();
        abort_unless($authorization->allows($user, $alliance, PermissionKey::AllianceManage), 403);
        $settings = AlliancePlatformSetting::query()->whereKey($alliance->id)->first();
        $apiAccessEnabled = $settings instanceof AlliancePlatformSetting
            ? (bool) $settings->api_access_enabled
            : true;
        $webhooksEnabled = $settings instanceof AlliancePlatformSetting
            ? (bool) $settings->webhooks_enabled
            : true;

        return Inertia::render('Alliance/Integrations/Manage', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => ['id' => (string) $alliance->id, 'name' => (string) $alliance->name],
            'settings' => [
                'apiAccessEnabled' => $apiAccessEnabled,
                'webhooksEnabled' => $webhooksEnabled,
            ],
            'limits' => $entitlements->limits($alliance),
            'allowedScopes' => CreateApiCredential::allowedScopes(),
            'credentials' => ApiCredential::query()
                ->where('alliance_id', $alliance->id)
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
                ->where('alliance_id', $alliance->id)
                ->latest()
                ->get()
                ->map(static fn (WebhookSubscription $subscription): array => [
                    'id' => (string) $subscription->id,
                    'name' => (string) $subscription->name,
                    'url' => (string) $subscription->url,
                    'events' => $subscription->events,
                    'active' => (bool) $subscription->is_active,
                    'revokedAt' => $subscription->revoked_at?->toIso8601String(),
                ])->all(),
            'recentDeliveries' => WebhookDelivery::query()
                ->where('alliance_id', $alliance->id)
                ->latest()
                ->limit(50)
                ->get()
                ->map(static fn (WebhookDelivery $delivery): array => [
                    'id' => (string) $delivery->id,
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
            'status' => $request->session()->get('status'),
        ]);
    }

    public function createCredential(Request $request, AllianceContext $context, CreateApiCredential $create): RedirectResponse
    {
        $user = $this->user($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1', 'max:3'],
            'scopes.*' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $issued = $create->handle(
            $context->alliance(),
            $user,
            (string) $validated['name'],
            array_values(array_map('strval', $validated['scopes'])),
            isset($validated['expires_at']) ? CarbonImmutable::parse((string) $validated['expires_at'], 'UTC') : null,
        );

        return back()
            ->with('issued_api_credential', [
                'id' => (string) $issued->credential->id,
                'name' => (string) $issued->credential->name,
                'token' => $issued->token,
            ])
            ->with('status', 'api-credential-created');
    }

    public function revokeCredential(
        Request $request,
        AllianceContext $context,
        string $credential,
        RevokeApiCredential $revoke,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $target = ApiCredential::query()->where('alliance_id', $alliance->id)->findOrFail($credential);
        $revoke->handle($alliance, $this->user($request), $target);

        return back()->with('status', 'api-credential-revoked');
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
            $context->alliance(),
            $this->user($request),
            (string) $validated['name'],
            (string) $validated['url'],
            array_values(array_map('strval', $validated['events'])),
        );

        return back()
            ->with('issued_webhook_secret', [
                'id' => (string) $subscription->id,
                'name' => (string) $subscription->name,
                'secret' => (string) $subscription->signing_secret,
            ])
            ->with('status', 'webhook-created');
    }

    public function revokeWebhook(
        Request $request,
        AllianceContext $context,
        string $subscription,
        RevokeWebhookSubscription $revoke,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $target = WebhookSubscription::query()->where('alliance_id', $alliance->id)->findOrFail($subscription);
        $revoke->handle($alliance, $this->user($request), $target);

        return back()->with('status', 'webhook-revoked');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
