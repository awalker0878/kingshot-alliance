<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Integrations\Models\ApiCredential;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RevokeApiCredential
{
    public function __construct(
        private AllianceMutationAuthority $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, ApiCredential $credential): ApiCredential
    {
        if ((string) $credential->alliance_id !== (string) $alliance->id) {
            throw new InvalidArgumentException('API credential does not belong to the active alliance.');
        }

        return DB::transaction(function () use ($alliance, $actor, $credential): ApiCredential {
            $authority = $this->mutations->require($actor, $alliance, AlliancePermission::Manage);
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
