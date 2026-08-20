<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Models\ExternalActorLink;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RevokeExternalActorLink
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $linkId): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $linkId): void {
            [$alliance, $actor] = $this->allianceAuthority->authorizeMemberExclusive($actorPlayerId, $allianceId);
            $link = ExternalActorLink::query()
                ->whereKey($linkId)
                ->where('alliance_id', $alliance->allianceId)
                ->where('player_id', $actor->playerId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($link->revoked_at !== null) {
                return;
            }

            $link->forceFill(['revoked_at' => now()])->save();
            $metadata = ['link_id' => (string) $link->id, 'provider' => $link->provider->value];
            $this->audit->record('integration.external_actor.revoked', $actor, $link, $alliance->allianceId, $metadata);
            $this->outbox->record('integration.external_actor.revoked', $alliance->allianceId, $link, $metadata);
        });
    }
}
