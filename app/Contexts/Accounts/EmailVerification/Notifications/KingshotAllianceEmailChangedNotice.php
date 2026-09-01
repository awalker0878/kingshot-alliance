<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\EmailVerification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class KingshotAllianceEmailChangedNotice extends Notification
{
    use Queueable;

    public function __construct(private readonly string $newEmail) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) __('accounts.mail.email_changed.subject'))
            ->view('mail.accounts.security', [
                'eyebrow' => __('accounts.mail.email_changed.eyebrow'),
                'heading' => __('accounts.mail.email_changed.heading'),
                'intro' => __('accounts.mail.email_changed.intro', ['email' => $this->newEmail]),
                'actionText' => __('accounts.mail.email_changed.action'),
                'actionUrl' => route('login'),
                'expiry' => null,
                'notice' => __('accounts.mail.email_changed.notice'),
            ])
            ->text('mail.accounts.security-text', [
                'heading' => __('accounts.mail.email_changed.heading'),
                'intro' => __('accounts.mail.email_changed.intro', ['email' => $this->newEmail]),
                'actionText' => __('accounts.mail.email_changed.action'),
                'actionUrl' => route('login'),
                'expiry' => null,
                'notice' => __('accounts.mail.email_changed.notice'),
            ]);
    }
}
