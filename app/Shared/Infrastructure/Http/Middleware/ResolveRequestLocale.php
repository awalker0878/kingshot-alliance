<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Middleware;

use App\Shared\Infrastructure\Http\AcceptLanguageLocale;
use App\Shared\Infrastructure\Http\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveRequestLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // The selected cookie is first, but never bypasses strict standalone
        // normalization. Raw Accept-Language ranges must be validated before
        // Symfony/Laravel can shorten malformed prefixes in getLanguages().
        // This preference carries no authority. No localStorage reaches PHP.
        $locale = SupportedLocale::normalize($request->cookie('kingshot_locale'))
            ?? AcceptLanguageLocale::firstSupported($request->headers->get('Accept-Language'));
        app()->setLocale($locale);

        return $next($request);
    }
}
