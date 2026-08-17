<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Services;

use App\Contexts\Alliance\Content\Queries\ContentStorageUsageQuery;
use App\Contexts\Alliance\Membership\Queries\MembershipStatisticsQuery;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class PlanEntitlementService
{
    public function __construct(
        private MembershipStatisticsQuery $memberships,
        private ContentStorageUsageQuery $storage,
    ) {}

    public function limit(string $allianceId, string $key): int
    {
        $planCode = DB::table('alliance_plan_assignments')->where('alliance_id', $allianceId)->value('plan_code');
        $planCode = is_string($planCode) && $planCode !== '' ? $planCode : 'standard';
        $value = DB::table('platform_plan_entitlements')
            ->where('plan_code', $planCode)
            ->where('entitlement_key', $key)
            ->value('limit_value');

        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'plan' => sprintf('The current plan does not define the %s entitlement.', $key),
            ]);
        }

        return max(0, (int) $value);
    }

    public function assertMemberCapacity(string $allianceId): void
    {
        $active = $this->memberships->activeCount($allianceId);
        $pendingInvitations = $this->memberships->pendingInvitationCount($allianceId);
        $this->assertBelow($active + $pendingInvitations, $this->limit($allianceId, 'members.max'), 'members');
    }

    public function assertStorageCapacity(string $allianceId, int $additionalBytes): void
    {
        $current = $this->storage->bytes($allianceId);
        $limit = $this->limit($allianceId, 'storage.bytes.max');
        if ($additionalBytes < 0 || $current + $additionalBytes > $limit) {
            throw ValidationException::withMessages([
                'media' => 'The alliance storage quota would be exceeded by this upload.',
            ]);
        }
    }

    public function assertApiCredentialCapacity(string $allianceId): void
    {
        $current = ApiCredential::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        $this->assertBelow($current, $this->limit($allianceId, 'api_credentials.max'), 'API credentials');
    }

    public function assertWebhookCapacity(string $allianceId): void
    {
        $current = WebhookSubscription::query()
            ->where('alliance_id', $allianceId)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->count();
        $this->assertBelow($current, $this->limit($allianceId, 'webhook_subscriptions.max'), 'webhook subscriptions');
    }

    /** @return array{members:int,storageBytes:int,apiCredentials:int,webhookSubscriptions:int} */
    public function limits(string $allianceId): array
    {
        return [
            'members' => $this->limit($allianceId, 'members.max'),
            'storageBytes' => $this->limit($allianceId, 'storage.bytes.max'),
            'apiCredentials' => $this->limit($allianceId, 'api_credentials.max'),
            'webhookSubscriptions' => $this->limit($allianceId, 'webhook_subscriptions.max'),
        ];
    }

    private function assertBelow(int $current, int $limit, string $label): void
    {
        if ($current >= $limit) {
            throw ValidationException::withMessages([
                'quota' => sprintf('The alliance has reached its plan limit for %s (%d).', $label, $limit),
            ]);
        }
    }
}
