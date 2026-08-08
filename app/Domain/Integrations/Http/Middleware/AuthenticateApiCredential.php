<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Http\Middleware;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\ValueObjects\TenantContextSnapshot;
use App\Domain\Integrations\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiCredential
{
    public function handle(Request $request, Closure $next, string $requiredScope = 'alliance:read'): Response
    {
        $token = $request->bearerToken();
        if (! is_string($token) || ! preg_match('/^ks_live_([a-f0-9]{12})\.([a-f0-9]{64})$/', $token, $matches)) {
            abort(401, 'A valid API credential is required.');
        }

        $credential = ApiCredential::query()
            ->where('prefix', $matches[1])
            ->first();

        if (! $credential instanceof ApiCredential
            || ! hash_equals($credential->secret_hash, hash('sha256', $matches[2]))
            || ! $credential->active()
            || ! $credential->allows($requiredScope)) {
            abort(401, 'The API credential is invalid, expired, revoked, or missing the required scope.');
        }

        $alliance = Alliance::query()->find($credential->alliance_id);
        if (! $alliance instanceof Alliance || $alliance->status !== AllianceStatus::Active) {
            abort(403, 'The alliance is not available for API access.');
        }

        if ($credential->last_used_at === null || $credential->last_used_at->lt(now()->subMinutes(5))) {
            $credential->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('alliance_id', (string) $alliance->id);
        $request->attributes->set('api_credential_id', (string) $credential->id);
        $request->attributes->set('tenant_context', TenantContextSnapshot::fromRequest($request));

        try {
            return $next($request);
        } finally {
            $request->attributes->remove('tenant_context');
        }
    }
}
