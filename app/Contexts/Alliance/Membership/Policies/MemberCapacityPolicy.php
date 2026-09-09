<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Policies;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use Illuminate\Validation\ValidationException;

final readonly class MemberCapacityPolicy
{
    public function __construct(private PlanEntitlementQuery $entitlements) {}

    public function assertCapacity(Alliance $alliance): void
    {
        if ($this->remainingCapacity($alliance) < 1) {
            $limit = $this->entitlements->limit((string) $alliance->id, 'members.max');
            throw ValidationException::withMessages(['quota' => sprintf('The alliance has reached its plan limit for members (%d).', $limit)]);
        }
    }

    public function remainingCapacity(Alliance $alliance): int
    {
        $active = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->count();
        $pending = Invitation::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->count();

        return max(0, $this->entitlements->limit((string) $alliance->id, 'members.max') - $active - $pending);
    }
}
