<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionCandidateState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionCandidate;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use Illuminate\Database\Eloquent\Collection;

final class KingdomIngestionQuery
{
    /** @return Collection<int, KingdomIngestionSubscription> */
    public function subscriptionsForAlliance(Alliance $alliance): Collection
    {
        return KingdomIngestionSubscription::query()
            ->where('alliance_id', $alliance->id)
            ->with(['kingdom', 'latestBatch'])
            ->withCount([
                'candidates as pending_candidates_count' => static fn ($query) => $query->where('state', KingdomIngestionCandidateState::Pending->value),
                'candidates as quarantined_candidates_count' => static fn ($query) => $query->where('state', KingdomIngestionCandidateState::Quarantined->value),
                'candidates as rejected_candidates_count' => static fn ($query) => $query->where('state', KingdomIngestionCandidateState::Rejected->value),
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /** @return Collection<int, KingdomIngestionCandidate> */
    public function recentCandidatesForAlliance(Alliance $alliance, int $limit = 50): Collection
    {
        return KingdomIngestionCandidate::query()
            ->where('alliance_id', $alliance->id)
            ->with('subscription:id,alliance_id,adapter_key,adapter_version')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
