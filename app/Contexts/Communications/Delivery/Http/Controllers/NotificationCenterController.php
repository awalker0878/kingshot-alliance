<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Communications\Delivery\Actions\DeleteNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Actions\UpdateNotificationInboxState;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationCenterController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $player = $this->ownedPlayerOrNull($user);
        $playerId = $player?->playerId;

        $deliveries = NotificationDelivery::query()
            ->where('recipient_user_id', $user->id)
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
            ->where('recipient_user_id', $user->id)
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
            ->where('recipient_user_id', $user->id)
            ->when($playerId === null, static fn ($query) => $query->whereNull('player_id'))
            ->when($playerId !== null, static fn ($query) => $query->where('player_id', $playerId))
            ->get()
            ->mapWithKeys(static fn (NotificationPreference $preference): array => [
                $preference->notification_type.':'.$preference->channel => (bool) $preference->enabled,
            ])->all();

        return Inertia::render('Accounts/Notifications/Index', [
            'user' => ['name' => $user->name, 'email' => $user->email],
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
            'status' => $request->session()->get('status'),
        ]);
    }

    public function saveEndpoint(Request $request, SaveNotificationEndpoint $save): RedirectResponse
    {
        $user = $this->user($request);
        $player = $this->ownedPlayer($user);
        $validated = $request->validate([
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'label' => ['required', 'string', 'max:100'],
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:64'],
        ]);
        $channel = DeliveryChannel::from((string) $validated['channel']);
        $save->handle((int) $user->id, $player->playerId, $channel, (string) $validated['label'], [
            'webhook_url' => (string) ($validated['webhook_url'] ?? ''),
            'bot_token' => (string) ($validated['bot_token'] ?? ''),
            'chat_id' => (string) ($validated['chat_id'] ?? ''),
        ]);

        return back()->with('status', 'notification-endpoint-saved');
    }

    public function deleteEndpoint(
        Request $request,
        string $endpoint,
        DeleteNotificationEndpoint $delete,
    ): RedirectResponse {
        $user = $this->user($request);
        $delete->handle((int) $user->id, $this->ownedPlayer($user)->playerId, $endpoint);

        return back()->with('status', 'notification-endpoint-deleted');
    }

    public function setPreference(Request $request, SetNotificationPreference $set): RedirectResponse
    {
        $user = $this->user($request);
        $player = $this->ownedPlayer($user);
        $validated = $request->validate([
            'notification_type' => ['required', 'string', Rule::in(SetNotificationPreference::NOTIFICATION_TYPES)],
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'enabled' => ['required', 'boolean'],
        ]);
        $set->handle(
            (int) $user->id,
            $player->playerId,
            (string) $validated['notification_type'],
            DeliveryChannel::from((string) $validated['channel']),
            (bool) $validated['enabled'],
        );

        return back()->with('status', 'notification-preference-updated');
    }

    public function markRead(
        Request $request,
        string $delivery,
        UpdateNotificationInboxState $state,
    ): RedirectResponse {
        $user = $this->user($request);
        $state->markRead($delivery, (int) $user->id, $this->ownedPlayerOrNull($user)?->playerId);

        return back();
    }

    public function dismiss(
        Request $request,
        string $delivery,
        UpdateNotificationInboxState $state,
    ): RedirectResponse {
        $user = $this->user($request);
        $state->dismiss($delivery, (int) $user->id, $this->ownedPlayerOrNull($user)?->playerId);

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function ownedPlayer(User $user): PlayerReference
    {
        $player = $this->ownedPlayerOrNull($user);
        abort_unless($player instanceof PlayerReference, 409, 'Select a Governor before configuring notification channels.');

        return $player;
    }

    private function ownedPlayerOrNull(User $user): ?PlayerReference
    {
        $player = $this->playerContext->playerOrNull();

        return $player instanceof PlayerReference && $player->userId === (int) $user->id ? $player : null;
    }
}
