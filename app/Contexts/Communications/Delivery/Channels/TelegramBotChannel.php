<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Channels;

use App\Contexts\Communications\Delivery\Contracts\ExternalDeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TelegramBotChannel implements ExternalDeliveryChannel
{
    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::Telegram;
    }

    public function deliver(DeliveryAttempt $attempt, array $configuration): DeliveryOutcome
    {
        $botToken = $configuration['bot_token'] ?? '';
        $chatId = $configuration['chat_id'] ?? '';
        if ($botToken === '' || $chatId === '') {
            return DeliveryOutcome::failed('Telegram bot token or chat ID is not configured.', false);
        }

        $message = $attempt->title()."\n".$attempt->body();
        if ($attempt->actionUrl() !== null) {
            $message .= "\n".rtrim((string) config('app.url'), '/').$attempt->actionUrl();
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(10)
                ->post('https://api.telegram.org/bot'.$botToken.'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => mb_substr($message, 0, 4096),
                    'disable_web_page_preview' => true,
                ]);
        } catch (ConnectionException $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), true);
        } catch (Throwable $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), false);
        }

        if ($response->successful() && $response->json('ok') !== false) {
            return DeliveryOutcome::delivered();
        }

        $retryAfter = $response->json('parameters.retry_after');
        $retryAt = is_numeric($retryAfter)
            ? CarbonImmutable::now('UTC')->addSeconds(max(1, (int) $retryAfter))
            : null;
        $retryable = $response->status() === 429 || $response->status() >= 500;

        return DeliveryOutcome::failed('Telegram returned HTTP '.$response->status().'.', $retryable, $retryAt);
    }
}
