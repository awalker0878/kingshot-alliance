<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Services\PlayerMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Models\PlayerFormation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class DeletePlayerFormation
{
    public function __construct(
        private PlayerMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, PlayerFormation $formation): void
    {
        if ((string) $formation->player_id !== (string) $actor->id) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $formation): void {
            $context = $this->authority->require($actor);

            $locked = PlayerFormation::query()
                ->whereKey($formation->id)
                ->where('player_id', $context->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = [
                'player_id' => (string) $context->actor->id,
                'formation_id' => (string) $locked->id,
            ];
            $this->audit->record('rally.player_formation.deleted', $context->actor, $locked, null, $metadata);
            $this->outbox->record('rally.player_formation.deleted', null, $locked, $metadata, partitionKey: 'player:'.$context->actor->id);
            $locked->delete();
        });
    }
}
