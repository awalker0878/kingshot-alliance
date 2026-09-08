<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\EmailVerification;

use App\Contexts\Accounts\Credentials\Notifications\ResetKingshotAlliancePassword;
use App\Contexts\Accounts\EmailVerification\Notifications\KingshotAllianceEmailChangedNotice;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyKingshotAllianceEmail;
use App\Contexts\Accounts\EmailVerification\Notifications\VerifyPendingKingshotAllianceEmail;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\TestCase;

final class AccountSecurityMailRenderingV3Test extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function messages(): iterable
    {
        foreach (['verification', 'pending', 'changed', 'reset'] as $purpose) {
            yield $purpose => [$purpose];
        }
    }

    #[DataProvider('messages')]
    public function test_real_html_and_plain_text_parts_preserve_the_action_url_in_their_own_format(string $purpose): void
    {
        $user = new User;
        $user->forceFill(['id' => 42, 'email' => 'fixture@example.test']);
        $notification = match ($purpose) {
            'verification' => new VerifyKingshotAllianceEmail,
            'pending' => new VerifyPendingKingshotAllianceEmail(42, sha1('pending@example.test')),
            'changed' => new KingshotAllianceEmailChangedNotice('new@example.test'),
            'reset' => new ResetKingshotAlliancePassword('synthetic-reset-token'),
        };
        $mail = $notification->toMail($user);
        self::assertSame(['html' => 'mail.accounts.security', 'text' => 'mail.accounts.security-text'], $mail->view);
        $url = $mail->viewData['actionUrl'];
        $html = view($mail->view['html'], $mail->viewData)->render();
        $plainText = view($mail->view['text'], $mail->viewData)->render();

        self::assertStringContainsString('KINGSHOT ALLIANCE', $html);
        self::assertStringContainsString('KINGSHOT ALLIANCE', $plainText);
        self::assertStringContainsString('href="'.e($url).'"', $html);
        self::assertStringContainsString($url, $plainText);
        self::assertStringNotContainsString('&amp;', $plainText);
        self::assertStringContainsString((string) $mail->viewData['eyebrow'], $html);

        if (in_array($purpose, ['verification', 'pending'], true)) {
            $matches = [];
            self::assertSame(1, preg_match('~https?://[^\s]+~', $plainText, $matches));
            self::assertTrue(URL::hasValidSignature(Request::create($matches[0])));
        }
    }
}
