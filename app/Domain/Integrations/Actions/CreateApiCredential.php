<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Integrations\Models\ApiCredential;
use App\Domain\Integrations\ValueObjects\IssuedApiCredential;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Platform\Services\PlanEntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
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
        private AllianceAuthorization $authorization,
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
            $lockedAlliance = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->firstOrFail();
            $lockedActor = Player::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            if (! $this->authorization->allows($lockedActor, $lockedAlliance, PermissionKey::AllianceManage)) {
                throw new AuthorizationException;
            }

            $settings = AlliancePlatformSetting::query()->whereKey($lockedAlliance->id)->lockForUpdate()->first();
            if ($settings !== null && ! $settings->api_access_enabled) {
                throw ValidationException::withMessages(['api' => 'API access is disabled for this alliance.']);
            }

            // The Alliance row lock serializes capacity-sensitive credential creation.
            $this->entitlements->assertApiCredentialCapacity($lockedAlliance);

            $prefix = strtolower(bin2hex(random_bytes(6)));
            $secret = bin2hex(random_bytes(32));
            $credential = ApiCredential::query()->create([
                'alliance_id' => $lockedAlliance->id,
                'name' => $name,
                'prefix' => $prefix,
                'secret_hash' => hash('sha256', $secret),
                'scopes' => $scopes,
                'expires_at' => $expiresAt?->utc(),
                'created_by_player_id' => $lockedActor->id,
            ]);

            $this->audit->record('integration.api-credential.created', $lockedActor, $credential, $lockedAlliance, [
                'credential_id' => $credential->id,
                'prefix' => $prefix,
                'scopes' => $scopes,
                'expires_at' => $expiresAt?->toIso8601String(),
            ]);
            $this->outbox->record('integration.api-credential.created', $lockedAlliance->id, $credential, [
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
