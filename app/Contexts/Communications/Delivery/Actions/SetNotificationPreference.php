<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationPreference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SetNotificationPreference
{
    public const ACCOUNT_SCOPE = 'account';

    /** @var list<string> */
    public const NOTIFICATION_TYPES = [
        'account.security',
        'alliance.announcement',
        'event.reminder',
        'gift_code.expiring',
        'gift_code.available',
        'gift_code.trust_changed',
        'intelligence.change',
        'king_perks.reminder',
        'officer.brief',
    ];

    public function __construct(private PlayerReferenceQuery $players) {}

    public function handle(
        int $recipientUserId,
        ?string $playerId,
        string $notificationType,
        DeliveryChannel $channel,
        bool $enabled,
    ): void {
        if (! in_array($notificationType, self::NOTIFICATION_TYPES, true)) {
            throw ValidationException::withMessages(['notification_type' => 'Choose a supported notification type.']);
        }

        DB::transaction(function () use ($recipientUserId, $playerId, $notificationType, $channel, $enabled): void {
            if ($playerId !== null) {
                $actor = $this->players->lockCurrent($playerId);
                if ($actor->userId !== $recipientUserId) {
                    throw ValidationException::withMessages(['player' => 'The selected Governor no longer belongs to this account.']);
                }
            }

            NotificationPreference::query()->updateOrCreate(
                [
                    'recipient_user_id' => $recipientUserId,
                    'scope_key' => $playerId ?? self::ACCOUNT_SCOPE,
                    'notification_type' => $notificationType,
                    'channel' => $channel->value,
                ],
                [
                    'player_id' => $playerId,
                    'enabled' => $enabled,
                ],
            );
        });
    }

    public function resetGovernorOverride(
        int $recipientUserId,
        string $playerId,
        string $notificationType,
        DeliveryChannel $channel,
    ): void {
        if (! in_array($notificationType, self::NOTIFICATION_TYPES, true)) {
            throw ValidationException::withMessages(['notification_type' => 'Choose a supported notification type.']);
        }

        DB::transaction(function () use ($recipientUserId, $playerId, $notificationType, $channel): void {
            $actor = $this->players->lockCurrent($playerId);
            if ($actor->userId !== $recipientUserId) {
                throw ValidationException::withMessages(['player' => 'The selected Governor no longer belongs to this account.']);
            }

            NotificationPreference::query()
                ->where('recipient_user_id', $recipientUserId)
                ->where('scope_key', $playerId)
                ->where('notification_type', $notificationType)
                ->where('channel', $channel->value)
                ->delete();
        });
    }
}
