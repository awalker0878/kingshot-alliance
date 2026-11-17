<?php

declare(strict_types=1);

// Run inside the built runtime image, without mounting a checkout over /var/www/html.
use App\Contexts\Accounts\Credentials\Notifications\ResetKingshotAlliancePassword;
use App\Contexts\Accounts\EmailVerification\Notifications\KingshotAllianceEmailChangedNotice;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyPendingKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\SupportedLocale;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

require dirname(__DIR__).'/vendor/autoload.php';
try {
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    function resolved(string $value, string $context): void
    {
        check($value !== '' && ! str_contains($value, 'accounts.mail.'), $context.': missing translation');
        check(preg_match('/:(?:attribute|minutes|email)\b/', $value) !== 1, $context.': unresolved parameter');
    }

    foreach (['NOTICE.md', 'LICENSE-Laravel-Lang.md'] as $notice) {
        check(is_file(lang_path($notice)), 'Missing lang/'.$notice);
    }

    $translator = app('translator');
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'Image probe', 'email' => 'image-probe@example.test']);
    foreach (SupportedLocale::CODES as $locale) {
        check(is_file(lang_path($locale.'.json')), 'Missing JSON catalogue: '.$locale);
        $json = json_decode(file_get_contents(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
        check(is_array($json) && count($json) > 0, 'Empty JSON catalogue: '.$locale);
        foreach ($json as $key => $value) {
            check($translator->get($key, [], $locale, false) === $value, 'JSON resolution: '.$locale.'/'.$key);
        }
        foreach (['accounts.mail.verify.subject', 'auth.failed', 'passwords.reset', 'validation.required'] as $key) {
            // English framework groups are supplied by the locked Laravel dependency.
            check($translator->hasForLocale($key, $locale), 'Missing local group: '.$locale.'/'.$key);
        }
        $app->setLocale($locale);
        $validation = Validator::make([], ['email' => 'required'])->errors()->first('email');
        resolved($validation, $locale.'/validation');
        if ($locale !== 'en') {
            check($validation !== 'The email field is required.', $locale.': English validation fallback');
        }

        foreach ([
            new VerifyKingshotAllianceEmail,
            new VerifyPendingKingshotAllianceEmail(1, sha1('image-probe@example.test')),
            new KingshotAllianceEmailChangedNotice('new@example.test'),
            new ResetKingshotAlliancePassword('image-probe-not-a-real-token'),
        ] as $notification) {
            $mail = $notification->toMail($user);
            resolved($mail->subject, $locale.'/subject');
            foreach ($mail->view as $format => $view) {
                $body = view($view, $mail->viewData)->render();
                resolved($body, $locale.'/'.$format);
                if ($format === 'html') {
                    check(str_contains($body, 'lang="'.$locale.'"'), $locale.': incorrect mail language');
                    check(str_contains($body, 'dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"'), $locale.': incorrect mail direction');
                }
            }
        }
        echo $locale.": catalogues, validation and four HTML/text account messages passed\n";
    }

    // Exercise Laravel's serialized queued notification path with captured locale and
    // an English worker default. The array transport never contacts an SMTP service.
    config(['mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array']]);
    $app->setLocale('en');
    foreach (['fr', 'ar'] as $locale) {
        $recipient = (new AnonymousNotifiable)->route('mail', 'image-probe@example.test');
        $notification = (new VerifyPendingKingshotAllianceEmail(1, sha1('image-probe@example.test')))->locale($locale);
        $job = unserialize(serialize(new SendQueuedNotifications($recipient, $notification, ['mail'])));
        $job->handle(app(ChannelManager::class));
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        check($message->getSubject() === $translator->get('accounts.mail.email_change_verify.subject', [], $locale, false), $locale.': queued subject locale');
        check(str_contains($message->getHtmlBody(), 'lang="'.$locale.'"'), $locale.': queued body locale');
        resolved($message->getTextBody(), $locale.'/queued text');
        check($app->getLocale() === 'en', 'Notification leaked locale into the worker');
    }
    echo "Serialized notifications retained French/Arabic and restored the worker locale.\n";

} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
