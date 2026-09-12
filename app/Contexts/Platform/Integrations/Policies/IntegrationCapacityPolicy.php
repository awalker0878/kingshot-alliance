<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Policies;

use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use App\Contexts\Platform\Integrations\Queries\IntegrationUsageQuery;
use Illuminate\Validation\ValidationException;

final readonly class IntegrationCapacityPolicy
{
    public function __construct(private PlanEntitlementQuery $entitlements, private IntegrationUsageQuery $usage) {}

    public function assertApiCredentialCapacity(string $allianceId): void
    {
        $current = $this->usage->activeCredentials($allianceId);
        $this->assertBelow($current, $this->entitlements->limit($allianceId, 'api_credentials.max'), 'API credentials');
    }

    public function assertWebhookCapacity(string $allianceId): void
    {
        $current = $this->usage->activeWebhooks($allianceId);
        $this->assertBelow($current, $this->entitlements->limit($allianceId, 'webhook_subscriptions.max'), 'webhook subscriptions');
    }

    private function assertBelow(int $current, int $limit, string $label): void
    {
        if ($current >= $limit) {
            throw ValidationException::withMessages(['quota' => sprintf('The alliance has reached its plan limit for %s (%d).', $label, $limit)]);
        }
    }
}
