<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveNotificationEndpoint
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
    ) {}

    /** @param array<string, string> $configuration */
    public function handle(
        int $recipientUserId,
        string $playerId,
        DeliveryChannel $channel,
        string $label,
        array $configuration,
    ): string {
        if (! $channel->isExternal()) {
            throw ValidationException::withMessages(['channel' => 'Choose an external delivery channel.']);
        }

        $label = trim($label);
        if ($label === '') {
            throw ValidationException::withMessages(['label' => 'Endpoint label is required.']);
        }

        $configuration = $this->validatedConfiguration($channel, $configuration);

        return DB::transaction(function () use ($recipientUserId, $playerId, $channel, $label, $configuration): string {
            $actor = $this->players->lockCurrent($playerId);
            if ($actor->userId !== $recipientUserId) {
                throw ValidationException::withMessages(['player' => 'The active Governor no longer belongs to this account.']);
            }

            $endpoint = NotificationEndpoint::query()->updateOrCreate(
                [
                    'recipient_user_id' => $recipientUserId,
                    'player_id' => $playerId,
                    'channel' => $channel->value,
                ],
                [
                    'label' => $label,
                    'configuration' => $configuration,
                    'enabled' => true,
                    'last_verified_at' => null,
                    'last_error' => null,
                ],
            );

            $this->audit->record('notification.endpoint.saved', $actor, $endpoint, metadata: [
                'channel' => $channel->value,
                'label' => $label,
            ]);

            return (string) $endpoint->id;
        });
    }

    /**
     * @param  array<string, string>  $configuration
     * @return array<string, string>
     */
    private function validatedConfiguration(DeliveryChannel $channel, array $configuration): array
    {
        if ($channel === DeliveryChannel::Discord) {
            $url = trim($configuration['webhook_url'] ?? '');
            $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
            $path = (string) parse_url($url, PHP_URL_PATH);
            if ($scheme !== 'https'
                || ! in_array($host, ['discord.com', 'www.discord.com', 'discordapp.com'], true)
                || ! preg_match('#^/api(?:/v\d+)?/webhooks/\d+/[A-Za-z0-9._-]+$#', $path)) {
                throw ValidationException::withMessages([
                    'webhook_url' => 'Enter an official HTTPS Discord webhook URL.',
                ]);
            }

            return ['webhook_url' => $url];
        }

        $botToken = trim($configuration['bot_token'] ?? '');
        $chatId = trim($configuration['chat_id'] ?? '');
        if (! preg_match('/^\d{6,12}:[A-Za-z0-9_-]{30,}$/', $botToken)) {
            throw ValidationException::withMessages(['bot_token' => 'Enter a valid Telegram bot token.']);
        }
        if (! preg_match('/^-?\d{5,20}$/', $chatId)) {
            throw ValidationException::withMessages(['chat_id' => 'Enter a valid Telegram chat ID.']);
        }

        return ['bot_token' => $botToken, 'chat_id' => $chatId];
    }
}
