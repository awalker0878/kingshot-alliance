<?php

declare(strict_types=1);

namespace Tests\Contexts\Accounts\EmailVerification\Feature;

use App\Shared\Infrastructure\Http\Middleware\ResolveRequestLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class RequestLocaleTest extends TestCase
{
    public function test_selected_language_takes_precedence_over_the_browser_language(): void
    {
        $request = Request::create('/', cookies: ['kingshot_locale' => 'ar'], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA,en;q=0.8']);
        (new ResolveRequestLocale)->handle($request, static function (): Response {
            self::assertSame('ar', app()->getLocale());
            $message = Validator::make([], ['email' => 'required'])->errors()->first('email');
            self::assertNotSame('The email field is required.', $message);
            self::assertStringNotContainsString(':attribute', $message);

            return new Response;
        });
    }

    public function test_unknown_preferences_fall_back_to_browser_language_then_english_on_the_next_request(): void
    {
        $middleware = new ResolveRequestLocale;
        $request = Request::create('/', cookies: ['kingshot_locale' => '../invalid'], server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA,en;q=0.8']);
        $middleware->handle($request, static fn (): Response => new Response);
        self::assertSame('fr', app()->getLocale());
        $middleware->handle(Request::create('/'), static fn (): Response => new Response);
        self::assertSame('en', app()->getLocale());
    }
}
