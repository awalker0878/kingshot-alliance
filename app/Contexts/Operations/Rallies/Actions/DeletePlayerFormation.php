<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Rallies\Models\PlayerFormation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class DeletePlayerFormation
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $formationId): void
    {
        DB::transaction(function () use ($actorPlayerId, $formationId): void {
            $actor = $this->players->lockCurrent($actorPlayerId);
            $formation = PlayerFormation::query()
                ->whereKey($formationId)
                ->where('player_id', $actor->playerId)
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = ['player_id' => $actor->playerId, 'formation_id' => (string) $formation->id];
            $this->audit->record('rally.player_formation.deleted', $actor, $formation, null, $metadata);
            $this->outbox->record('rally.player_formation.deleted', null, $formation, $metadata, partitionKey: 'player:'.$actor->playerId);
            $formation->delete();
        });
    }
}
