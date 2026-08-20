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
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
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

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $player = $this->ownedPlayerOrNull($userId);
        $playerId = $player?->playerId;

        $deliveries = NotificationDelivery::query()
            ->where('recipient_user_id', $userId)
            ->whereNull('dismissed_at')
            ->where(static function ($query) use ($playerId): void {
                $query->whereNull('player_id');
                if ($playerId !== null) {
                    $query->orWhere('player_id', $playerId);
                }
            })
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(static function (NotificationDelivery $delivery): array {
                $metadata = is_array($delivery->metadata) ? $delivery->metadata : [];

                return [
                    'id' => (string) $delivery->id,
                    'type' => (string) $delivery->notification_type,
                    'channel' => (string) $delivery->channel,
                    'status' => $delivery->status->value,
                    'title' => is_string($metadata['title'] ?? null) ? $metadata['title'] : 'Kingshot reminder',
                    'body' => is_string($metadata['body'] ?? null) ? $metadata['body'] : null,
                    'actionUrl' => is_string($metadata['action_url'] ?? null) ? $metadata['action_url'] : null,
                    'dueAt' => $delivery->due_at?->toIso8601String(),
                    'sentAt' => $delivery->sent_at?->toIso8601String(),
                    'readAt' => $delivery->read_at?->toIso8601String(),
                    'lastError' => $delivery->last_error,
                ];
            })->all();

        $endpoints = NotificationEndpoint::query()
            ->where('recipient_user_id', $userId)
            ->when($playerId === null, static fn ($query) => $query->whereNull('player_id'))
            ->when($playerId !== null, static fn ($query) => $query->where('player_id', $playerId))
            ->orderBy('channel')
            ->get()
            ->map(static fn (NotificationEndpoint $endpoint): array => [
                'id' => (string) $endpoint->id,
                'channel' => $endpoint->channel->value,
                'label' => (string) $endpoint->label,
                'enabled' => (bool) $endpoint->enabled,
                'lastVerifiedAt' => $endpoint->last_verified_at?->toIso8601String(),
                'lastError' => $endpoint->last_error,
            ])->all();

        $preferences = NotificationPreference::query()
            ->where('recipient_user_id', $userId)
            ->when($playerId === null, static fn ($query) => $query->whereNull('player_id'))
            ->when($playerId !== null, static fn ($query) => $query->where('player_id', $playerId))
            ->get()
            ->mapWithKeys(static fn (NotificationPreference $preference): array => [
                $preference->notification_type.':'.$preference->channel => (bool) $preference->enabled,
            ])->all();

        return Inertia::render('Accounts/Notifications/Index', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'player' => $player === null ? null : [
                'id' => $player->playerId,
                'name' => $player->currentName,
            ],
            'deliveries' => $deliveries,
            'endpoints' => $endpoints,
            'preferences' => $preferences,
            'notificationTypes' => SetNotificationPreference::NOTIFICATION_TYPES,
            'channels' => array_map(static fn (DeliveryChannel $channel): array => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'external' => $channel->isExternal(),
            ], DeliveryChannel::cases()),
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
        ]);
        $channel = DeliveryChannel::from((string) $validated['channel']);
        $save->handle($userId, $player->playerId, $channel, (string) $validated['label'], [
            'webhook_url' => (string) ($validated['webhook_url'] ?? ''),
            'bot_token' => (string) ($validated['bot_token'] ?? ''),
            'chat_id' => (string) ($validated['chat_id'] ?? ''),
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
        $player = $this->ownedPlayer($userId);
        $validated = $request->validate([
            'notification_type' => ['required', 'string', Rule::in(SetNotificationPreference::NOTIFICATION_TYPES)],
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'enabled' => ['required', 'boolean'],
        ]);
        $set->handle(
            $userId,
            $player->playerId,
            (string) $validated['notification_type'],
            DeliveryChannel::from((string) $validated['channel']),
            (bool) $validated['enabled'],
        );

        return back()->with('actionReceipt', $this->receipt('notification-preference-updated'));
    }

    public function markRead(
        Request $request,
        string $delivery,
        UpdateNotificationInboxState $state,
    ): RedirectResponse {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $state->markRead($delivery, $userId, $this->ownedPlayerOrNull($userId)?->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-marked-read'));
    }

    public function dismiss(
        Request $request,
        string $delivery,
        UpdateNotificationInboxState $state,
    ): RedirectResponse {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $state->dismiss($delivery, $userId, $this->ownedPlayerOrNull($userId)?->playerId);

        return back()->with('actionReceipt', $this->receipt('notification-dismissed'));
    }

    public function previewBulkInboxUpdate(
        Request $request,
        PreviewNotificationInboxBulkAction $preview,
    ): RedirectResponse {
        $user = $this->user($request);
        $userId = $this->userId($user);
        $validated = $this->validateBulkInboxUpdate($request);
        /** @var non-empty-list<string> $deliveryIds */
        $deliveryIds = $validated['delivery_ids'];

        return back()->with('notificationBulkPreview', $preview->handle(
            $userId,
            $this->ownedPlayerOrNull($userId)?->playerId,
            $deliveryIds,
            (string) $validated['operation'],
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
        /** @var non-empty-list<string> $deliveryIds */
        $deliveryIds = $validated['delivery_ids'];
        $result = $bulkUpdate->handle(
            $user,
            $userId,
            $this->ownedPlayerOrNull($userId)?->playerId,
            $deliveryIds,
            (string) $validated['operation'],
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

    /** @return array{delivery_ids: non-empty-list<string>, operation: string} */
    private function validateBulkInboxUpdate(Request $request): array
    {
        /** @var array{delivery_ids: non-empty-list<string>, operation: string} $validated */
        $validated = $request->validate([
            'delivery_ids' => ['required', 'array', 'min:1', 'max:50'],
            'delivery_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'operation' => ['required', 'string', Rule::in([
                PreviewNotificationInboxBulkAction::MARK_READ,
                PreviewNotificationInboxBulkAction::DISMISS,
            ])],
        ]);

        return $validated;
    }
}
