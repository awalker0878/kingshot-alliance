<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RevokeApiCredential
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, ApiCredential $credential): ApiCredential
    {
        if ((string) $credential->alliance_id !== (string) $alliance->id) {
            throw new InvalidArgumentException('API credential does not belong to the active alliance.');
        }

        return DB::transaction(function () use ($alliance, $actor, $credential): ApiCredential {
            $authority = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->mutations->authorizeContext($authority, AlliancePermission::Manage);
            $currentAlliance = $authority->alliance;
            $currentActor = $authority->actor;

            $locked = ApiCredential::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($credential->id);

            if ($locked->revoked_at !== null) {
                return $locked;
            }

            $locked->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('integration.api-credential.revoked', $currentActor, $locked, $currentAlliance, [
                'credential_id' => $locked->id,
                'prefix' => $locked->prefix,
            ]);
            $this->outbox->record('integration.api-credential.revoked', $currentAlliance->id, $locked, [
                'credential_id' => $locked->id,
            ]);

            return $locked->refresh();
        });
    }
}
