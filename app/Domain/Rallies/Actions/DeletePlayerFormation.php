<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Models\PlayerFormation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class DeletePlayerFormation
{
    public function __construct(private PlayerContext $context, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    public function handle(Player $actor, PlayerFormation $formation): void
    {
        $active = $this->context->playerOrNull();
        if (! $active instanceof Player
            || (string) $active->id !== (string) $actor->id
            || (string) $formation->player_id !== (string) $actor->id) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $formation, $active): void {
            $locked = PlayerFormation::query()->whereKey($formation->id)->where('player_id', $active->id)->lockForUpdate()->firstOrFail();
            $metadata = ['player_id' => (string) $active->id, 'formation_id' => (string) $locked->id];
            $this->audit->record('rally.player_formation.deleted', $actor, $locked, null, $metadata);
            $this->outbox->record('rally.player_formation.deleted', null, $locked, $metadata, partitionKey: 'player:'.$active->id);
            $locked->delete();
        });
    }
}
