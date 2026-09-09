<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Policies;

use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use Illuminate\Validation\ValidationException;

final readonly class IntegrationCapacityPolicy
{
    public function __construct(private PlanEntitlementQuery $entitlements) {}

    public function assertApiCredentialCapacity(string $allianceId): void
    {
        $current = ApiCredential::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        $this->assertBelow($current, $this->entitlements->limit($allianceId, 'api_credentials.max'), 'API credentials');
    }

    public function assertWebhookCapacity(string $allianceId): void
    {
        $current = WebhookSubscription::query()
            ->where('alliance_id', $allianceId)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();
        $this->assertBelow($current, $this->entitlements->limit($allianceId, 'webhook_subscriptions.max'), 'webhook subscriptions');
    }

    private function assertBelow(int $current, int $limit, string $label): void
    {
        if ($current >= $limit) {
            throw ValidationException::withMessages(['quota' => sprintf('The alliance has reached its plan limit for %s (%d).', $label, $limit)]);
        }
    }
}
