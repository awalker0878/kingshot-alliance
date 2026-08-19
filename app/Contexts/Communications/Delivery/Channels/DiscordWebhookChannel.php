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

final class DiscordWebhookChannel implements ExternalDeliveryChannel
{
    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::Discord;
    }

    public function deliver(DeliveryAttempt $attempt, array $configuration): DeliveryOutcome
    {
        $webhookUrl = $configuration['webhook_url'] ?? '';
        if ($webhookUrl === '') {
            return DeliveryOutcome::failed('Discord webhook URL is not configured.', false);
        }

        $content = '**'.$attempt->title()."**\n".$attempt->body();
        if ($attempt->actionUrl() !== null) {
            $content .= "\n".rtrim((string) config('app.url'), '/').$attempt->actionUrl();
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(10)
                ->post($webhookUrl, [
                    'content' => mb_substr($content, 0, 2000),
                    'allowed_mentions' => ['parse' => []],
                ]);
        } catch (ConnectionException $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), true);
        } catch (Throwable $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), false);
        }

        if ($response->successful()) {
            return DeliveryOutcome::delivered();
        }

        $retryAfter = $response->header('Retry-After');
        $retryAt = is_numeric($retryAfter)
            ? CarbonImmutable::now('UTC')->addSeconds(max(1, (int) ceil((float) $retryAfter)))
            : null;
        $retryable = $response->status() === 429 || $response->status() >= 500;

        return DeliveryOutcome::failed('Discord returned HTTP '.$response->status().'.', $retryable, $retryAt);
    }
}
