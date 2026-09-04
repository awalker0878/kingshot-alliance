<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Communications\Delivery\Actions\BulkUpdateNotificationInbox;
use App\Contexts\Communications\Delivery\Actions\DeleteNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\PreviewNotificationInboxBulkAction;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationInboxState;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\Communications\Delivery\Queries\NotificationInboxQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class NotificationCenterController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function index(Request $request, NotificationInboxQuery $inbox): Response
    {
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
            'notificationBulkPreview' => $request->session()->get('notificationBulkPreview'),
            'notificationBulkResult' => $request->session()->get('notificationBulkResult'),
        ]);
    }

    public function saveEndpoint(Request $request, SaveNotificationEndpoint $save): RedirectResponse
    {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $player = $this->ownedPlayer($userId);
        $validated = $request->validate([
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'label' => ['required', 'string', 'max:100'],
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:64'],
            'endpoint' => ['nullable', 'string', 'max:2048'],
            'p256dh' => ['nullable', 'string', 'max:255'],
            'auth' => ['nullable', 'string', 'max:255'],
        ]);
        $channel = DeliveryChannel::from((string) $validated['channel']);
        $save->handle($userId, $player->playerId, $channel, (string) $validated['label'], [
            'webhook_url' => (string) ($validated['webhook_url'] ?? ''),
            'bot_token' => (string) ($validated['bot_token'] ?? ''),
            'chat_id' => (string) ($validated['chat_id'] ?? ''),
            'endpoint' => (string) ($validated['endpoint'] ?? ''),
            'p256dh' => (string) ($validated['p256dh'] ?? ''),
            'auth' => (string) ($validated['auth'] ?? ''),
        ]);

        return back()->with('actionReceipt', $this->receipt('notification-endpoint-saved'));
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

    public function markRead(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        return $this->updateMessageState($request, $message, $state, PreviewNotificationInboxBulkAction::MARK_READ);
    }

    public function markUnread(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        return $this->updateMessageState($request, $message, $state, PreviewNotificationInboxBulkAction::MARK_UNREAD);
    }

    public function archive(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        return $this->updateMessageState($request, $message, $state, PreviewNotificationInboxBulkAction::ARCHIVE);
    }

    public function restore(Request $request, string $message, UpdateNotificationInboxState $state): RedirectResponse
    {
        return $this->updateMessageState($request, $message, $state, PreviewNotificationInboxBulkAction::RESTORE);
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

    private function updateMessageState(
        Request $request,
        string $message,
        UpdateNotificationInboxState $state,
        string $operation,
    ): RedirectResponse {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $playerId = $this->ownedPlayerOrNull($userId)?->playerId;
        $receipt = match ($operation) {
            PreviewNotificationInboxBulkAction::MARK_READ => $this->markReadState($state, $message, $userId, $playerId),
            PreviewNotificationInboxBulkAction::MARK_UNREAD => $this->markUnreadState($state, $message, $userId, $playerId),
            PreviewNotificationInboxBulkAction::ARCHIVE => $this->archiveState($state, $message, $userId, $playerId),
            default => $this->restoreState($state, $message, $userId, $playerId),
        };

        return back()->with('actionReceipt', $this->receipt($receipt));
    }

    private function markReadState(UpdateNotificationInboxState $state, string $message, int $userId, ?string $playerId): string
    {
        $state->markRead($message, $userId, $playerId);

        return 'notification-marked-read';
    }

    private function markUnreadState(UpdateNotificationInboxState $state, string $message, int $userId, ?string $playerId): string
    {
        $state->markUnread($message, $userId, $playerId);

        return 'notification-marked-unread';
    }

    private function archiveState(UpdateNotificationInboxState $state, string $message, int $userId, ?string $playerId): string
    {
        $state->archive($message, $userId, $playerId);

        return 'notification-archived';
    }

    private function restoreState(UpdateNotificationInboxState $state, string $message, int $userId, ?string $playerId): string
    {
        $state->restore($message, $userId, $playerId);

        return 'notification-restored';
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
     *   view?: string,
     *   type?: string|null,
     *   scope?: string,
     *   delivery_status?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   cursor?: string|null,
     *   limit?: int
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
