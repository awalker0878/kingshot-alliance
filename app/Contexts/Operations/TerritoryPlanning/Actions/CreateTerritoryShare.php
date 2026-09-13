<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationWriteState;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CreateTerritoryShare
{
    public function __construct(private TerritoryCollaborationWriteState $state, private TerritoryPlanningAuthorization $authorization,
        private TerritoryPlanWriteState $players, private TerritoryPlanQuery $plans, private AuditRecorder $audit) {}

    /**
     * @param list<string> $allianceKeys
     * @return array{id:string,token:string,expires_at:string}
     */
    public function handle(string $actorPlayerId, string $planId, string $revisionId, string $recipientPlayerId, array $allianceKeys, CarbonImmutable $expiresAt): array
    {
        Validator::make(['keys' => $allianceKeys], ['keys' => ['required', 'array', 'min:1', 'max:50'], 'keys.*' => ['required', 'string', 'max:120', 'distinct']])->validate();
        if (! $expiresAt->isFuture() || $expiresAt->greaterThan(now()->addDays(31))) {
            throw ValidationException::withMessages(['expires_at' => 'Share expiry must be within 31 days.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $planId, $revisionId, $recipientPlayerId, $allianceKeys, $expiresAt): array {
            $context = $this->state->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            $player = $this->players->lockLinkedPlayer($recipientPlayerId);
            if ($player->kingdomId !== $context->plan->kingdom_id || $player->userId === null) {
                throw ValidationException::withMessages(['recipient_player_id' => 'Choose an account-backed Governor in the current Kingdom.']);
            }
            $this->plans->authorizeView($recipientPlayerId, $planId);
            $revision = TerritoryPlanRevision::query()->where('territory_plan_id', $planId)->whereKey($revisionId)->firstOrFail();
            $available = array_column($revision->snapshot['alliances'] ?? [], 'key');
            if (array_diff($allianceKeys, $available) !== []) {
                throw ValidationException::withMessages(['alliance_keys' => 'Choose Alliance layers from the published revision.']);
            }
            $token = bin2hex(random_bytes(32));
            $share = TerritoryShare::query()->create([
                'territory_plan_id' => $planId, 'territory_plan_revision_id' => $revisionId,
                'recipient_player_id' => $recipientPlayerId, 'created_by_player_id' => $actorPlayerId,
                'alliance_keys' => array_values($allianceKeys), 'token_hash' => hash('sha256', $token), 'expires_at' => $expiresAt,
            ]);
            $this->audit->record('territory.share.created', $context->actor, $context->plan, $context->plan->owner_alliance_id,
                ['share_id' => (string) $share->id, 'revision_id' => $revisionId, 'recipient_player_id' => $recipientPlayerId]);

            return ['id' => (string) $share->id, 'token' => $token, 'expires_at' => $expiresAt->toIso8601String()];
        });
    }
}
