<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Queries;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class AssistantObservationQuery
{
    public function __construct(private AllianceIntelligenceAuthorization $authorization) {}

    /**
     * Return only the minimum observation projection that an authorized Assistant read may use.
     * Authorization is evaluated before observation rows are queried.
     *
     * @return list<array{id:string,trackingId:string,observedName:string,observedTag:?string,power:?int,memberCount:?int,capturedAt:string,source:string}>
     */
    public function search(string $actorPlayerId, string $allianceId, string $search, int $limit = 5): array
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $limit = max(1, min($limit, 10));
        $needle = '%'.mb_strtolower($search).'%';
        $candidates = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('invalidated_at')
            ->where(static function (Builder $query) use ($needle): void {
                $query->whereRaw('LOWER(observed_name) LIKE ?', [$needle])
                    ->orWhereRaw("LOWER(COALESCE(observed_tag, '')) LIKE ?", [$needle]);
            })
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(min(50, $limit * 6))
            ->get();

        $seen = [];
        $rows = [];
        foreach ($candidates as $observation) {
            $trackingId = (string) $observation->tracked_kingdom_alliance_id;
            if (isset($seen[$trackingId])) {
                continue;
            }

            $seen[$trackingId] = true;
            $rows[] = [
                'id' => (string) $observation->id,
                'trackingId' => $trackingId,
                'observedName' => (string) $observation->observed_name,
                'observedTag' => $observation->observed_tag,
                'power' => $observation->power,
                'memberCount' => $observation->member_count,
                'capturedAt' => $observation->captured_at->toIso8601String(),
                'source' => (string) $observation->source,
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }
}
