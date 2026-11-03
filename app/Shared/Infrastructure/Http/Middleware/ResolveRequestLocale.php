<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Middleware;

use App\Shared\Infrastructure\Http\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveRequestLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // This preference is deliberately readable by the language selector.
        // It carries no identity or authority and is restricted to this allowlist.
        $locale = SupportedLocale::normalize($request->cookie('kingshot_locale'));
        if ($locale === null) {
            foreach ($request->getLanguages() as $language) {
                $locale = SupportedLocale::normalize($language);
                if ($locale !== null) {
                    break;
                }
            }
        }

        app()->setLocale($locale ?? 'en');

        return $next($request);
    }
}
