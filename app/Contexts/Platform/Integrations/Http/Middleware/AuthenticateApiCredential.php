<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Middleware;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\ValueObjects\TenantContextSnapshot;
use App\Contexts\Platform\Integrations\Actions\RecordApiCredentialUse;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateApiCredential
{
    public function __construct(private RecordApiCredentialUse $recordUse) {}

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

        $this->recordUse->handle($credential);

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
