<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Services;

use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanEntitlementService
{
    public function limit(Alliance $alliance, string $key): int
    {
        $planCode = DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->id)->value('plan_code');
        $planCode = is_string($planCode) && $planCode !== '' ? $planCode : 'standard';
        $value = DB::table('platform_plan_entitlements')->where('plan_code', $planCode)->where('entitlement_key', $key)->value('limit_value');
        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['plan' => sprintf('The current plan does not define the %s entitlement.', $key)]);
        }

        return max(0, (int) $value);
    }

    public function assertMemberCapacity(Alliance $alliance): void
    {
        $active = AllianceMembership::query()->where('alliance_id', $alliance->id)->where('status', MembershipStatus::Active->value)->count();
        $pendingInvitations = Invitation::query()->where('alliance_id', $alliance->id)->where('status', InvitationStatus::Pending->value)->where('expires_at', '>', now())->count();
        $this->assertBelow($active + $pendingInvitations, $this->limit($alliance, 'members.max'), 'members');
    }

    public function assertStorageCapacity(Alliance $alliance, int $additionalBytes): void
    {
        $current = (int) MediaAsset::query()->where('alliance_id', $alliance->id)->sum('size_bytes');
        $limit = $this->limit($alliance, 'storage.bytes.max');
        if ($additionalBytes < 0 || $current + $additionalBytes > $limit) {
            throw ValidationException::withMessages(['media' => 'The alliance storage quota would be exceeded by this upload.']);
        }
    }

    public function assertApiCredentialCapacity(Alliance $alliance): void
    {
        $current = ApiCredential::query()->where('alliance_id', $alliance->id)->whereNull('revoked_at')->where(static function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();
        $this->assertBelow($current, $this->limit($alliance, 'api_credentials.max'), 'API credentials');
    }

    public function assertWebhookCapacity(Alliance $alliance): void
    {
        $current = WebhookSubscription::query()->where('alliance_id', $alliance->id)->where('is_active', true)->whereNull('revoked_at')->count();
        $this->assertBelow($current, $this->limit($alliance, 'webhook_subscriptions.max'), 'webhook subscriptions');
    }

    public function limits(Alliance $alliance): array
    {
        return ['members' => $this->limit($alliance, 'members.max'), 'storageBytes' => $this->limit($alliance, 'storage.bytes.max'), 'apiCredentials' => $this->limit($alliance, 'api_credentials.max'), 'webhookSubscriptions' => $this->limit($alliance, 'webhook_subscriptions.max')];
    }

    private function assertBelow(int $current, int $limit, string $label): void
    {
        if ($current >= $limit) {
            throw ValidationException::withMessages(['quota' => sprintf('The alliance has reached its plan limit for %s (%d).', $label, $limit)]);
        }
    }
}
