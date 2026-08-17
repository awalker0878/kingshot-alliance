<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RevokeApiCredential
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $credentialId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $credentialId): string {
            [$currentAlliance, $currentActor] = $this->allianceAuthority->authorizeManagerActive($actorPlayerId, $allianceId);

            $locked = ApiCredential::query()
                ->where('alliance_id', $currentAlliance->allianceId)
                ->lockForUpdate()
                ->findOrFail($credentialId);

            if ($locked->revoked_at !== null) {
                return (string) $locked->id;
            }

            $locked->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('integration.api-credential.revoked', $currentActor, $locked, $currentAlliance->allianceId, [
                'credential_id' => $locked->id,
                'prefix' => $locked->prefix,
            ]);
            $this->outbox->record('integration.api-credential.revoked', $currentAlliance->allianceId, $locked, [
                'credential_id' => $locked->id,
            ]);

            return (string) $locked->id;
        });
    }
}
