<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\ValueObjects\IssuedApiCredential;
use App\Contexts\Platform\Models\AlliancePlatformSetting;
use App\Contexts\Platform\Services\PlanEntitlementService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateApiCredential
{
    /** @var list<string> */
    private const ALLOWED_SCOPES = [
        'alliance:read',
        'events:read',
        'contributions:read',
    ];

    public function __construct(
        private AllianceAuthorization $mutations,
        private PlanEntitlementService $entitlements,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $scopes */
    public function handle(
        Alliance $alliance,
        Player $actor,
        string $name,
        array $scopes,
        ?CarbonImmutable $expiresAt = null,
    ): IssuedApiCredential {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'API credential name is required.']);
        }

        $scopes = array_values(array_unique(array_map('strval', $scopes)));
        if ($scopes === [] || array_diff($scopes, self::ALLOWED_SCOPES) !== []) {
            throw ValidationException::withMessages(['scopes' => 'At least one supported API scope is required.']);
        }

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw ValidationException::withMessages(['expires_at' => 'API credential expiry must be in the future.']);
        }

        return DB::transaction(function () use ($alliance, $actor, $name, $scopes, $expiresAt): IssuedApiCredential {
            // Credential capacity is Alliance-wide, so acquire the exclusive mutation boundary.
            $authority = $this->mutations->requireExclusive($actor, $alliance, AlliancePermission::Manage);
            $currentAlliance = $authority->alliance;
            $currentActor = $authority->actor;

            $settings = AlliancePlatformSetting::query()->whereKey($currentAlliance->id)->lockForUpdate()->first();
            if ($settings !== null && ! $settings->api_access_enabled) {
                throw ValidationException::withMessages(['api' => 'API access is disabled for this alliance.']);
            }

            $this->entitlements->assertApiCredentialCapacity($currentAlliance);

            $prefix = strtolower(bin2hex(random_bytes(6)));
            $secret = bin2hex(random_bytes(32));
            $credential = ApiCredential::query()->create([
                'alliance_id' => $currentAlliance->id,
                'name' => $name,
                'prefix' => $prefix,
                'secret_hash' => hash('sha256', $secret),
                'scopes' => $scopes,
                'expires_at' => $expiresAt?->utc(),
                'created_by_player_id' => $currentActor->id,
            ]);

            $this->audit->record('integration.api-credential.created', $currentActor, $credential, $currentAlliance, [
                'credential_id' => $credential->id,
                'prefix' => $prefix,
                'scopes' => $scopes,
                'expires_at' => $expiresAt?->toIso8601String(),
            ]);
            $this->outbox->record('integration.api-credential.created', $currentAlliance->id, $credential, [
                'credential_id' => $credential->id,
                'prefix' => $prefix,
                'scopes' => $scopes,
            ]);

            return new IssuedApiCredential($credential, 'ks_live_'.$prefix.'.'.$secret);
        });
    }

    /** @return list<string> */
    public static function allowedScopes(): array
    {
        return self::ALLOWED_SCOPES;
    }
}
