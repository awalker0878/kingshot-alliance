<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAccessGrant;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryActivityRecorder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationWriteState;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class GrantTerritoryPlanAccess
{
    public function __construct(private TerritoryCollaborationWriteState $state, private TerritoryPlanningAuthorization $authorization,
        private TerritoryPlanWriteState $players, private TerritoryPlanQuery $plans,
        private TerritoryActivityRecorder $activities, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $planId, string $playerId, string $allianceKey, string $permission, CarbonImmutable $expiresAt): string
    {
        if (! in_array($permission, ['review', 'edit'], true) || ! $expiresAt->isFuture() || $expiresAt->greaterThan(now()->addDays(31))) {
            throw ValidationException::withMessages(['access' => 'Choose review or edit permission and an expiry within 31 days.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $planId, $playerId, $allianceKey, $permission, $expiresAt): string {
            $context = $this->state->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            $player = $this->players->lockLinkedPlayer($playerId);
            if ($player->kingdomId !== $context->plan->kingdom_id || $player->userId === null
                || ! $context->plan->planAlliances()->where('plan_key', $allianceKey)->exists()) {
                throw ValidationException::withMessages(['player_id' => 'Select an account-backed current Governor and a plan Alliance.']);
            }
            $this->plans->authorizeView($playerId, $planId);
            $grant = TerritoryPlanAccessGrant::query()->updateOrCreate([
                'territory_plan_id' => $planId, 'player_id' => $playerId, 'alliance_key' => $allianceKey,
            ], ['granted_by_player_id' => $actorPlayerId, 'permission' => $permission, 'expires_at' => $expiresAt, 'revoked_at' => null]);
            $this->audit->record('territory.access.granted', $context->actor, $context->plan, $context->plan->owner_alliance_id,
                ['grant_id' => (string) $grant->id, 'recipient_player_id' => $playerId, 'alliance_key' => $allianceKey, 'permission' => $permission]);
            $this->activities->record($context, 'review_requested', (string) $grant->id.':'.$context->plan->revision, [$playerId]);

            return (string) $grant->id;
        });
    }
}
