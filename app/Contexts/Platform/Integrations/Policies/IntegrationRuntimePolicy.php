<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Policies;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;

/** Current platform switches and source lifecycle apply to every integration handoff. */
final readonly class IntegrationRuntimePolicy
{
    public function __construct(private AllianceReferenceQuery $alliances, private KingdomReferenceQuery $kingdoms) {}

    public function allowsApi(string $allianceId): bool
    {
        return $this->available($allianceId, 'api_access_enabled');
    }

    public function allowsWebhooks(string $allianceId): bool
    {
        return $this->available($allianceId, 'webhooks_enabled');
    }

    private function available(string $allianceId, string $setting): bool
    {
        $alliance = $this->alliances->find($allianceId);
        if ($alliance === null || ! $alliance->active() || $this->kingdoms->findActive($alliance->kingdomId) === null) {
            return false;
        }
        $settings = AlliancePlatformSetting::query()->whereKey($allianceId)->first();

        return $settings === null || (bool) $settings->getAttribute($setting);
    }
}
