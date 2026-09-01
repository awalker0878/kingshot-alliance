<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class VerifyPendingKingshotAllianceEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly int $userId, private readonly string $hash) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);
        $url = URL::temporarySignedRoute(
            'profile.security.email.verify',
            now()->addMinutes($minutes),
            ['id' => $this->userId, 'hash' => $this->hash],
        );

        return (new MailMessage)
            ->subject((string) __('accounts.mail.email_change_verify.subject'))
            ->view('mail.accounts.security', [
                'eyebrow' => __('accounts.mail.email_change_verify.eyebrow'),
                'heading' => __('accounts.mail.email_change_verify.heading'),
                'intro' => __('accounts.mail.email_change_verify.intro'),
                'actionText' => __('accounts.mail.email_change_verify.action'),
                'actionUrl' => $url,
                'expiry' => __('accounts.mail.email_change_verify.expiry', ['minutes' => $minutes]),
                'notice' => __('accounts.mail.email_change_verify.notice'),
            ])
            ->text('mail.accounts.security-text', [
                'heading' => __('accounts.mail.email_change_verify.heading'),
                'intro' => __('accounts.mail.email_change_verify.intro'),
                'actionText' => __('accounts.mail.email_change_verify.action'),
                'actionUrl' => $url,
                'expiry' => __('accounts.mail.email_change_verify.expiry', ['minutes' => $minutes]),
                'notice' => __('accounts.mail.email_change_verify.notice'),
            ]);
    }
}
