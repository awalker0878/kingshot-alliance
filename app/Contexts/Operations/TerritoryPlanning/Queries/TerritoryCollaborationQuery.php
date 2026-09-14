<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryObjectComment;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAccessGrant;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanReview;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class TerritoryCollaborationQuery
{
    public function __construct(private TerritoryPlanWriteState $state, private TerritoryPlanningAuthorization $authorization,
        private TerritoryCollaborationAuthorization $collaboration, private TerritoryPlanSnapshotBuilder $snapshots) {}

    /** @return array<string,mixed> */
    public function get(string $actorPlayerId, string $planId, ?string $after = null, ?string $reviewBefore = null, ?string $grantAfter = null, ?string $shareBefore = null): array
    {
        return DB::transaction(function () use ($actorPlayerId, $planId, $after, $reviewBefore, $grantAfter, $shareBefore): array {
            $context = $this->state->lock($actorPlayerId, $planId);
            $this->authorization->authorizeView($context);
            $snapshot = $this->snapshots->build($context->plan);
            $checksum = $this->snapshots->checksum($snapshot);
            $manager = $this->collaboration->isManager($context);
            $comments = TerritoryObjectComment::query()->where('territory_plan_id', $planId)
                ->when($after !== null, static fn ($query) => $query->where('id', '>', $after))->orderBy('id')->limit(51)->get();
            $hasMore = $comments->count() > 50;
            $comments = $comments->take(50);
            $reviewRows = TerritoryPlanReview::query()->where('territory_plan_id', $planId)
                ->when($reviewBefore !== null, static fn ($query) => $query->where('id', '<', $reviewBefore))
                ->orderByDesc('id')->limit(51)->get();
            $reviewsMore = $reviewRows->count() > 50;
            $reviewRows = $reviewRows->take(50);
            $reviews = $reviewRows->map(static fn (TerritoryPlanReview $row): array => [...$row->toArray(), 'stale' => $row->head_revision !== $context->plan->revision || ! hash_equals((string) $row->snapshot_checksum, $checksum)])->all();
            $grantRows = TerritoryPlanAccessGrant::query()->where('territory_plan_id', $planId)
                ->when(! $manager, static fn ($query) => $query->where('player_id', $actorPlayerId))
                ->when($grantAfter !== null, static fn ($query) => $query->where('id', '>', $grantAfter))
                ->orderBy('id')->limit(101)->get();
            $grantsMore = $grantRows->count() > 100;
            $grantRows = $grantRows->take(100);
            $shareRows = TerritoryShare::query()->where('territory_plan_id', $planId)
                ->when(! $manager, static fn ($query) => $query->whereRaw('1 = 0'))
                ->when($shareBefore !== null, static fn ($query) => $query->where('id', '<', $shareBefore))
                ->orderByDesc('id')->limit(51)->get();
            $sharesMore = $shareRows->count() > 50;
            $shareRows = $shareRows->take(50);
            try {
                $this->collaboration->authorizeReview($context);
                $canReview = true;
            } catch (AuthorizationException) {
                $canReview = false;
            }
            $editableKeys = $manager ? array_column($snapshot['alliances'], 'key') : TerritoryPlanAccessGrant::query()
                ->where('territory_plan_id', $planId)->where('player_id', $actorPlayerId)->where('permission', 'edit')
                ->whereNull('revoked_at')->where('expires_at', '>', now())->limit(50)->pluck('alliance_key')->all();

            return ['current_revision' => $context->plan->revision, 'current_snapshot_checksum' => $checksum,
                'can_manage' => $manager, 'can_review' => $canReview, 'editable_alliance_keys' => $editableKeys, 'comments' => $comments->values()->toArray(), 'comments_after' => $hasMore ? $comments->last()?->id : null,
                'reviews' => array_values($reviews), 'reviews_before' => $reviewsMore ? $reviewRows->last()?->id : null,
                'grants' => $grantRows->values()->toArray(), 'grants_after' => $grantsMore ? $grantRows->last()?->id : null,
                'shares' => $shareRows->values()->toArray(), 'shares_before' => $sharesMore ? $shareRows->last()?->id : null];
        });
    }
}
