<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Channels;

use App\Contexts\Communications\Delivery\Contracts\ExternalDeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EmailDeliveryChannel implements ExternalDeliveryChannel
{
    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::Email;
    }

    public function deliver(DeliveryAttempt $attempt, array $configuration): DeliveryOutcome
    {
        $email = trim($configuration['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return DeliveryOutcome::failed('A verified notification email is not available.', false);
        }

        $body = $attempt->body();
        if ($attempt->actionUrl() !== null) {
            $body .= "\n\n".rtrim((string) config('app.url'), '/').$attempt->actionUrl();
        }

        try {
            Mail::raw(mb_substr($body, 0, 20000), function ($message) use ($email, $attempt): void {
                $message->to($email)->subject(mb_substr($attempt->title(), 0, 240));
            });
        } catch (Throwable $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), true);
        }

        return DeliveryOutcome::delivered();
    }
}
