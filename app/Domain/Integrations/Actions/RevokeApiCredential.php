<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Integrations\Models\ApiCredential;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
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
            $authority = $this->mutations->require($actor, $alliance, PermissionKey::AllianceManage);
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
