<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Queries;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Builder;

/** Bounded membership candidates, never a cached authorization decision. */
final class AllianceMemberAudienceQuery
{
    public function upperBound(string $allianceId): ?string
    {
        $id = $this->active($allianceId)->orderByDesc('id')->limit(1)->value('id');

        return is_string($id) ? $id : null;
    }

    /** @return list<string> */
    public function page(string $allianceId, ?string $after, ?string $through, int $limit): array
    {
        if ($through === null) {
            return [];
        }

        return array_values($this->active($allianceId)->where('id', '<=', $through)
            ->when($after !== null, fn (Builder $query) => $query->where('id', '>', $after))
            ->orderBy('id')->limit(max(1, min(25, $limit)))->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all());
    }

    /** @return Builder<AllianceMembership> */
    private function active(string $allianceId): Builder
    {
        return AllianceMembership::query()->where('alliance_id', $allianceId)
            ->where('status', MembershipStatus::Active->value);
    }
}
