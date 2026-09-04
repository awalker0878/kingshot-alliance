<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Accounts\Identity\Queries\AccountTimezoneQuery;
use App\Contexts\Communications\Delivery\Actions\BulkUpdateNotificationInbox;
use App\Contexts\Communications\Delivery\Actions\DeleteNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\PreviewNotificationInboxBulkAction;
use App\Contexts\Communications\Delivery\Actions\QueueNotificationEndpointTest;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationEndpointState;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Actions\SetNotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationInboxState;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\Communications\Delivery\Models\NotificationRoutingPolicy;
use App\Contexts\Communications\Delivery\Queries\NotificationInboxQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class NotificationCenterController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function index(
        Request $request,
        NotificationInboxQuery $inbox,
        AccountTimezoneQuery $timezones,
    ): Response {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $player = $this->ownedPlayerOrNull($userId);
        $playerId = $player?->playerId;
        $filters = $this->validateInboxFilters($request);
        $messages = $inbox->handle($userId, $playerId, $filters);

        $endpoints = NotificationEndpoint::query()
            ->where('recipient_user_id', $userId)
            ->when($playerId === null, static fn ($query) => $query->whereNull('player_id'))
            ->when($playerId !== null, static fn ($query) => $query->where('player_id', $playerId))
            ->orderBy('channel')
            ->orderBy('label')
            ->get()
            ->map(static fn (NotificationEndpoint $endpoint): array => [
                'id' => (string) $endpoint->id,
                'channel' => $endpoint->channel->value,
                'label' => (string) $endpoint->label,
                'enabled' => (bool) $endpoint->enabled,
                'healthStatus' => $endpoint->health_status->value,
                'lastVerifiedAt' => $endpoint->last_verified_at?->toIso8601String(),
                'lastSuccessfulDeliveryAt' => $endpoint->last_successful_delivery_at?->toIso8601String(),
                'lastFailedDeliveryAt' => $endpoint->last_failed_delivery_at?->toIso8601String(),
                'consecutiveFailures' => (int) $endpoint->consecutive_failures,
                'lastError' => $endpoint->last_error === null
                    ? null
                    : mb_substr((string) $endpoint->last_error, 0, 500),
            ])
            ->values()
            ->all();

        $preferences = NotificationPreference::query()
            ->where('recipient_user_id', $userId)
            ->where(static function ($query) use ($playerId): void {
                $query->where('scope_key', SetNotificationPreference::ACCOUNT_SCOPE);
                if ($playerId !== null) {
                    $query->orWhere('scope_key', $playerId);
                }
            })
            ->get()
            ->mapWithKeys(static fn (NotificationPreference $preference): array => [
                (string) $preference->scope_key.':'.(string) $preference->notification_type.':'.(string) $preference->channel => (bool) $preference->enabled,
            ])
            ->all();

        $routingPolicies = NotificationRoutingPolicy::query()
            ->where('recipient_user_id', $userId)
            ->where(static function ($query) use ($playerId): void {
                $query->where('scope_key', SetNotificationRoutingPolicy::ACCOUNT_SCOPE);
                if ($playerId !== null) {
                    $query->orWhere('scope_key', $playerId);
                }
            })
            ->get()
            ->mapWithKeys(static function (NotificationRoutingPolicy $policy): array {
                $settings = is_array($policy->settings) ? $policy->settings : [];

                return [(string) $policy->scope_key => [
                    'timezone' => (string) $policy->timezone,
                    'quietHoursEnabled' => (bool) $policy->quiet_hours_enabled,
                    'quietHoursStart' => $policy->quiet_hours_start,
                    'quietHoursEnd' => $policy->quiet_hours_end,
                    'allowUrgentDuringQuietHours' => (bool) $policy->allow_urgent_during_quiet_hours,
                    'mutedUntil' => $policy->muted_until?->toIso8601String(),
                    'digestCadence' => $policy->digest_cadence->value,
                    'dailyDigestTime' => is_string($settings['daily_digest_time'] ?? null)
                        ? $settings['daily_digest_time']
                        : '09:00',
                    'digestUrgent' => ($settings['digest_urgent'] ?? false) === true,
                ]];
            })
            ->all();

        return Inertia::render('Accounts/Notifications/Index', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'player' => $player === null ? null : [
                'id' => $player->playerId,
                'name' => $player->currentName,
            ],
            'inbox' => $messages,
            'inboxFilters' => $filters,
            'endpoints' => array_values($endpoints),
            'preferences' => $preferences,
            'routingPolicies' => $routingPolicies,
            'routingDefaults' => [
                'timezone' => $timezones->forUser($userId),
                'digestCadence' => DigestCadence::Immediate->value,
                'dailyDigestTime' => '09:00',
            ],
            'notificationTypes' => SetNotificationPreference::NOTIFICATION_TYPES,
            'channels' => array_map(static fn (DeliveryChannel $channel): array => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'external' => $channel->isExternal(),
                'storedEndpoint' => $channel->usesStoredEndpoint(),
            ], DeliveryChannel::cases()),
            'deliveryStatuses' => array_map(
                static fn (DeliveryStatus $status): string => $status->value,
                DeliveryStatus::cases(),
            ),
            'digestCadences' => array_map(
                static fn (DigestCadence $cadence): string => $cadence->value,
                DigestCadence::cases(),
            ),
            'notificationBulkPreview' => $request->session()->get('notificationBulkPreview'),
            'notificationBulkResult' => $request->session()->get('notificationBulkResult'),
        ]);
    }

    public function saveEndpoint(Request $request, SaveNotificationEndpoint $save): RedirectResponse
    {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $player = $this->ownedPlayer($userId);
        $validated = $this->validateEndpoint($request, false);
        $channel = DeliveryChannel::from($validated['channel']);
        $save->handle($userId, $player->playerId, $channel, $validated['label'], $this->endpointConfiguration($validated));

        return back()->with('actionReceipt', $this->receipt('notification-endpoint-saved'));
    }

    public function updateEndpoint(
        Request $request,
        string $endpoint,
        UpdateNotificationEndpoint $update,
    ): RedirectResponse {
        $userId = $this->userId($this->user($request));
        $player = $this->ownedPlayer($userId);
        $validated = $this->validateEndpoint($request, true);
        $update->handle(
            $userId,
            $player->playerId,
            $endpoint,
            $validated['label'],
            $this->endpointConfiguration($validated),
        );

        return back()->with('actionReceipt', $this->receipt('notification-endpoint-updated'));
    }

    public function setEndpointState(
        Request $request,
        string $endpoint,
        SetNotificationEndpointState $setState,
    ): RedirectResponse {
        $userId = $this->userId($this->user($request));
        $player = $this->ownedPlayer($userId);
        /** @var array{enabled:bool} $validated */
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $setState->handle($userId, $player->playerId, $endpoint, $validated['enabled']);

        return back()->with('actionReceipt', $this->receipt(
            $validated['enabled'] ? 'notification-endpoint-resumed' : 'notification-endpoint-paused',
        ));
    }

    public function testEndpoint(
        Request $request,
        string $endpoint,
        QueueNotificationEndpointTest $test,
    ): RedirectResponse {
        $userId = $this->userId($this->user($request));
        $test->handle($userId, $this->ownedPlayer($userId)->playerId, $endpoint);

        return back()->with('actionReceipt', $this->receipt('notification-endpoint-test-queued'));
    }

    public function reverifyEndpoint(
        Request $request,
        string $endpoint,
        QueueNotificationEndpointTest $test,
    ): RedirectResponse {
        $userId = $this->userId($this->user($request));
        $test->handle($userId, $this->ownedPlayer($userId)->playerId, $endpoint);

        return back()->with('actionReceipt', $this->receipt('notification-endpoint-reverify-queued'));
    }

    public function deleteEndpoint(
        Request $request,
        string $endpoint,
        DeleteNotificationEndpoint $delete,
    ): RedirectResponse {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $delete->handle($userId, $this->ownedPlayer($userId)->playerId, $endpoint);

        return back()->with('actionReceipt', $this->receipt('notification-endpoint-deleted'));
    }

    public function setPreference(Request $request, SetNotificationPreference $set): RedirectResponse
    {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $validated = $this->validatePreference($request);
        $playerId = $validated['scope'] === SetNotificationPreference::ACCOUNT_SCOPE
            ? null
            : $this->ownedPlayer($userId)->playerId;
        $set->handle(
            $userId,
            $playerId,
            $validated['notification_type'],
            DeliveryChannel::from($validated['channel']),
            $validated['enabled'],
        );

        return back()->with('actionReceipt', $this->receipt('notification-preference-updated'));
    }

    public function resetPreference(Request $request, SetNotificationPreference $set): RedirectResponse
    {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $player = $this->ownedPlayer($userId);
        $validated = $request->validate([
            'notification_type' => ['required', 'string', Rule::in(SetNotificationPreference::NOTIFICATION_TYPES)],
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
        ]);
        $set->resetGovernorOverride(
            $userId,
            $player->playerId,
            (string) $validated['notification_type'],
            DeliveryChannel::from((string) $validated['channel']),
        );

        return back()->with('actionReceipt', $this->receipt('notification-preference-reset'));
    }

    public function setRoutingPolicy(Request $request, SetNotificationRoutingPolicy $set): RedirectResponse
    {
        $userId = $this->userId($this->user($request));
        /** @var array{
         *   scope:string,timezone:string,quiet_hours_enabled:bool,quiet_hours_start:?string,quiet_hours_end:?string,
         *   allow_urgent_during_quiet_hours:bool,muted_until:?string,digest_cadence:string,daily_digest_time:?string,digest_urgent:bool
         * } $validated
         */
        $validated = $request->validate([
            'scope' => ['required', 'string', Rule::in([SetNotificationRoutingPolicy::ACCOUNT_SCOPE, NotificationInboxQuery::SCOPE_GOVERNOR])],
            'timezone' => ['required', 'timezone'],
            'quiet_hours_enabled' => ['required', 'boolean'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'allow_urgent_during_quiet_hours' => ['required', 'boolean'],
            'muted_until' => ['nullable', 'date'],
            'digest_cadence' => ['required', Rule::enum(DigestCadence::class)],
            'daily_digest_time' => ['nullable', 'date_format:H:i'],
            'digest_urgent' => ['required', 'boolean'],
        ]);
        $playerId = $validated['scope'] === SetNotificationRoutingPolicy::ACCOUNT_SCOPE
            ? null
            : $this->ownedPlayer($userId)->playerId;
        $mutedUntil = is_string($validated['muted_until']) && $validated['muted_until'] !== ''
            ? CarbonImmutable::parse($validated['muted_until'])
            : null;
        $set->handle(
            recipientUserId: $userId,
            playerId: $playerId,
            timezone: $validated['timezone'],
            quietHoursEnabled: $validated['quiet_hours_enabled'],
            quietHoursStart: $validated['quiet_hours_start'],
            quietHoursEnd: $validated['quiet_hours_end'],
            allowUrgentDuringQuietHours: $validated['allow_urgent_during_quiet_hours'],
            mutedUntil: $mutedUntil,
            digestCadence: DigestCadence::from($validated['digest_cadence']),
            dailyDigestTime: $validated['daily_digest_time'],
            digestUrgent: $validated['digest_urgent'],
        );

        return back()->with('actionReceipt', $this->receipt('notification-routing-policy-updated'));
    }

    public function resetRoutingPolicy(Request $request, SetNotificationRoutingPolicy $set): RedirectResponse
    {
        $userId = $this->userId($this->user($request));
        $set->resetGovernorOverride($userId, $this->ownedPlayer($userId)->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-routing-policy-reset'));
    }

    public function markRead(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        $userId = $this->userId($this->user($request));
        $state->markRead($message, $userId, $this->ownedPlayerOrNull($userId)?->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-marked-read'));
    }

    public function markUnread(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        $userId = $this->userId($this->user($request));
        $state->markUnread($message, $userId, $this->ownedPlayerOrNull($userId)?->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-marked-unread'));
    }

    public function archive(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        $userId = $this->userId($this->user($request));
        $state->archive($message, $userId, $this->ownedPlayerOrNull($userId)?->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-archived'));
    }

    public function restore(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        $userId = $this->userId($this->user($request));
        $state->restore($message, $userId, $this->ownedPlayerOrNull($userId)?->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-restored'));
    }

    public function previewBulkInboxUpdate(
        Request $request,
        PreviewNotificationInboxBulkAction $preview,
    ): RedirectResponse {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $validated = $this->validateBulkInboxUpdate($request);

        return back()->with('notificationBulkPreview', $preview->handle(
            $userId,
            $this->ownedPlayerOrNull($userId)?->playerId,
            $validated['message_ids'],
            $validated['operation'],
        ));
    }

    public function bulkInboxUpdate(
        Request $request,
        BulkUpdateNotificationInbox $bulkUpdate,
    ): RedirectResponse {
        $user = $this->user($request);
        if (! $user instanceof AuditActor) {
            throw new LogicException('Authenticated accounts must provide an audit identity.');
        }

        $userId = $this->userId($user);
        $validated = $this->validateBulkInboxUpdate($request);
        $result = $bulkUpdate->handle(
            $user,
            $userId,
            $this->ownedPlayerOrNull($userId)?->playerId,
            $validated['message_ids'],
            $validated['operation'],
        )->toArray();

        return back()
            ->with('notificationBulkResult', $result)
            ->with('actionReceipt', $this->receipt('notification-bulk-inbox-completed', [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
            ]));
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }

    private function userId(AuthenticatedAccount $user): int
    {
        $identifier = $user->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return (int) $identifier;
    }

    private function ownedPlayer(int $userId): PlayerReference
    {
        $player = $this->ownedPlayerOrNull($userId);
        abort_unless($player instanceof PlayerReference, 409, 'Select a Governor before configuring notification channels.');

        return $player;
    }

    private function ownedPlayerOrNull(int $userId): ?PlayerReference
    {
        $player = $this->playerContext->playerOrNull();

        return $player instanceof PlayerReference && $player->userId === $userId ? $player : null;
    }

    /**
     * @return array{
     *   view?: string,type?: string|null,scope?: string,delivery_status?: string|null,date_from?: string|null,
     *   date_to?: string|null,cursor?: string|null,limit?: int
     * }
     */
    private function validateInboxFilters(Request $request): array
    {
        /** @var array<string,mixed> $validated */
        $validated = $request->validate([
            'view' => ['sometimes', 'string', Rule::in(NotificationInboxQuery::VIEWS)],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(SetNotificationPreference::NOTIFICATION_TYPES)],
            'scope' => ['sometimes', 'string', Rule::in(NotificationInboxQuery::SCOPES)],
            'delivery_status' => ['sometimes', 'nullable', Rule::enum(DeliveryStatus::class)],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return $validated;
    }

    /** @return array{notification_type:string,channel:string,enabled:bool,scope:string} */
    private function validatePreference(Request $request): array
    {
        /** @var array{notification_type:string,channel:string,enabled:bool,scope:string} $validated */
        $validated = $request->validate([
            'notification_type' => ['required', 'string', Rule::in(SetNotificationPreference::NOTIFICATION_TYPES)],
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'enabled' => ['required', 'boolean'],
            'scope' => ['required', 'string', Rule::in([SetNotificationPreference::ACCOUNT_SCOPE, NotificationInboxQuery::SCOPE_GOVERNOR])],
        ]);

        return $validated;
    }

    /**
     * @return array{
     *   channel:string,label:string,webhook_url:?string,bot_token:?string,chat_id:?string,
     *   endpoint:?string,p256dh:?string,auth:?string
     * }
     */
    private function validateEndpoint(Request $request, bool $updating): array
    {
        /** @var array{
         *   channel:string,label:string,webhook_url:?string,bot_token:?string,chat_id:?string,
         *   endpoint:?string,p256dh:?string,auth:?string
         * } $validated
         */
        $validated = $request->validate([
            'channel' => [$updating ? 'sometimes' : 'required', Rule::enum(DeliveryChannel::class)],
            'label' => ['required', 'string', 'max:100'],
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:64'],
            'endpoint' => ['nullable', 'string', 'max:2048'],
            'p256dh' => ['nullable', 'string', 'max:255'],
            'auth' => ['nullable', 'string', 'max:255'],
        ]);
        if (! isset($validated['channel'])) {
            $validated['channel'] = DeliveryChannel::Discord->value;
        }

        return $validated;
    }

    /**
     * @param  array{webhook_url:?string,bot_token:?string,chat_id:?string,endpoint:?string,p256dh:?string,auth:?string}  $validated
     * @return array<string,string>
     */
    private function endpointConfiguration(array $validated): array
    {
        return [
            'webhook_url' => (string) ($validated['webhook_url'] ?? ''),
            'bot_token' => (string) ($validated['bot_token'] ?? ''),
            'chat_id' => (string) ($validated['chat_id'] ?? ''),
            'endpoint' => (string) ($validated['endpoint'] ?? ''),
            'p256dh' => (string) ($validated['p256dh'] ?? ''),
            'auth' => (string) ($validated['auth'] ?? ''),
        ];
    }

    /** @return array{message_ids: non-empty-list<string>, operation: string} */
    private function validateBulkInboxUpdate(Request $request): array
    {
        /** @var array{message_ids: non-empty-list<string>, operation: string} $validated */
        $validated = $request->validate([
            'message_ids' => ['required', 'array', 'min:1', 'max:50'],
            'message_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'operation' => ['required', 'string', Rule::in(PreviewNotificationInboxBulkAction::OPERATIONS)],
        ]);

        return $validated;
    }
}
