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

    public function test_accept_language_quality_weights_are_parsed_before_single_tag_normalization(): void
    {
        $fixturePath = dirname(__DIR__, 5).'/resources/data/localization/locale-normalization-cases.json';
        $raw = file_get_contents($fixturePath);
        self::assertNotFalse($raw);
        $headers = json_decode($raw, true, 512, JSON_THROW_ON_ERROR)['headers'];
        $middleware = new ResolveRequestLocale;

        foreach ($headers as $case) {
            $request = Request::create(
                '/',
                cookies: $case['cookie'] === null ? [] : ['kingshot_locale' => $case['cookie']],
                server: ['HTTP_ACCEPT_LANGUAGE' => $case['header']],
            );

            // Request::getLanguages() is the header parser. q-weights and commas
            // must never be passed to the single-tag normalizer.
            foreach ($request->getLanguages() as $tag) {
                self::assertStringNotContainsString(';q=', $tag);
                self::assertStringNotContainsString(',', $tag);
            }

            $response = $middleware->handle($request, static function () use ($case): Response {
                self::assertSame($case['expected'], app()->getLocale(), $case['header']);

                return new Response('locale resolved', 200);
            });
            self::assertSame(200, $response->getStatusCode(), 'Rejected inputs must fall back, never fail with 4xx.');
        }
    }

    public function test_malformed_raw_accept_language_headers_degrade_to_english_with_http_200(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 5).'/resources/data/localization/locale-normalization-cases.json');
        self::assertNotFalse($raw);
        $cases = json_decode($raw, true, 512, JSON_THROW_ON_ERROR)['malformedHeaders'];
        $middleware = new ResolveRequestLocale;

        foreach ($cases as $case) {
            $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => $case['header']]);
            $response = $middleware->handle($request, static function () use ($case): Response {
                self::assertSame('en', app()->getLocale(), $case['description']);

                return new Response('fallback', 200);
            });
            self::assertSame(200, $response->getStatusCode(), $case['description']);
        }
    }
