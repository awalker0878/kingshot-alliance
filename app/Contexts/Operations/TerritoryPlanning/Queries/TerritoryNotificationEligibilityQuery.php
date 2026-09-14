<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryActivity;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAccessGrant;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class TerritoryNotificationEligibilityQuery
{
    public function __construct(private TerritoryPlanQuery $plans) {}

    public function allows(NotificationSource $source, PlayerReference $player): bool
    {
        if ($source->notificationType !== 'territory.activity' || $source->subjectType !== 'territory_activity'
            || $source->subjectId === null || $player->userId !== $source->recipientUserId || $player->playerId !== $source->playerId) {
            return false;
        }
        $activity = TerritoryActivity::query()->find($source->subjectId);
        if (! $activity instanceof TerritoryActivity || $source->metadataString('plan_id') !== $activity->territory_plan_id) {
            return false;
        }
        try {
            $this->plans->authorizeView($player->playerId, (string) $activity->territory_plan_id);
        } catch (AuthorizationException|ModelNotFoundException) {
            return false;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 403 && $exception->getStatusCode() !== 404) {
                throw $exception;
            }

            return false;
        }
        if ($activity->kind === 'review_requested') {
            $grantId = explode(':', (string) $activity->meaning_key)[0];

            return TerritoryPlanAccessGrant::query()->whereKey($grantId)->where('territory_plan_id', $activity->territory_plan_id)
                ->where('player_id', $player->playerId)->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
        }
        if ($activity->kind === 'published' && ! TerritoryPlanRevision::query()->whereKey($activity->meaning_key)
            ->where('territory_plan_id', $activity->territory_plan_id)->exists()) {
            return false;
        }

        return in_array($player->playerId, $activity->recipient_player_ids, true)
            || ($activity->recipient_player_ids === [] && $this->candidateQuery((string) $activity->territory_plan_id)->where('player_id', $player->playerId)->exists());
    }

    /** The Workflow owns transaction composition; this query holds the owner row until queue receipts and cursor commit together.
     * @return array{id:string,plan_id:string,kind:string,after:?string,recipient_player_ids:list<string>,next_after:?string,complete:bool}|null
     */
    public function lockNextPage(int $limit = 50): ?array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Activity pages must be acquired in a transaction.');
        }
        $activity = TerritoryActivity::query()->whereNull('completed_at')->orderBy('id')->lock('for update skip locked')->first();
        if (! $activity instanceof TerritoryActivity) {
            return null;
        }
        $limit = max(1, min(100, $limit));
        $after = $activity->after_player_id;
        $explicit = $activity->recipient_player_ids;
        if ($explicit !== []) {
            sort($explicit);
            $ids = array_values(array_filter($explicit, static fn (string $id): bool => $after === null || strcmp($id, $after) > 0));
            $ids = array_slice($ids, 0, $limit + 1);
        } else {
            $ids = $this->candidateQuery((string) $activity->territory_plan_id)
                ->when($after !== null, static fn ($query) => $query->where('player_id', '>', $after))
                ->orderBy('player_id')->limit($limit + 1)->pluck('player_id')->all();
            $ids = array_values(array_filter($ids, static fn (mixed $id): bool => is_string($id) && $id !== ''));
        }
        $complete = count($ids) <= $limit;
        $ids = array_slice($ids, 0, $limit);

        return ['id' => (string) $activity->id, 'plan_id' => (string) $activity->territory_plan_id,
            'kind' => (string) $activity->kind, 'after' => $after, 'recipient_player_ids' => $ids,
            'next_after' => $ids === [] ? $after : $ids[array_key_last($ids)], 'complete' => $complete];
    }

    private function candidateQuery(string $planId): Builder
    {
        $objects = TerritoryPlanObject::query()->where('territory_plan_id', $planId)->whereNotNull('player_id')->select('player_id')->toBase();
        $grants = TerritoryPlanAccessGrant::query()->where('territory_plan_id', $planId)->whereNull('revoked_at')->where('expires_at', '>', now())->select('player_id')->toBase();
        $creator = TerritoryPlan::query()->whereKey($planId)->selectRaw('created_by_player_id as player_id')->toBase();

        return DB::query()->fromSub($objects->union($grants)->union($creator), 'territory_recipients');
    }
}
