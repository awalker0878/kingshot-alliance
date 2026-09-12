<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Policies;

use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use Illuminate\Validation\ValidationException;

final readonly class StorageCapacityPolicy
{
    public function __construct(private PlanEntitlementQuery $entitlements) {}

    public function assertCapacity(Alliance $alliance, int $additionalBytes): void
    {
        $current = (int) MediaAsset::query()->where('alliance_id', $alliance->id)->sum('size_bytes');
        $limit = $this->entitlements->limit((string) $alliance->id, 'storage.bytes.max');
        if ($additionalBytes < 0 || $current + $additionalBytes > $limit) {
            throw ValidationException::withMessages(['media' => 'The alliance storage quota would be exceeded by this upload.']);
        }
    }
}
