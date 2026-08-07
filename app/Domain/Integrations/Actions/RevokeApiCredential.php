<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\ApiCredential;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RevokeApiCredential
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, ApiCredential $credential): ApiCredential
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::AllianceManage)) {
            throw new AuthorizationException;
        }

        if ($credential->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('API credential does not belong to the active alliance.');
        }

        if ($credential->revoked_at !== null) {
            return $credential;
        }

        return DB::transaction(function () use ($alliance, $actor, $credential): ApiCredential {
            $credential->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('integration.api-credential.revoked', $actor, $credential, $alliance, [
                'credential_id' => $credential->id,
                'prefix' => $credential->prefix,
            ]);
            $this->outbox->record('integration.api-credential.revoked', $alliance->id, $credential, [
                'credential_id' => $credential->id,
            ]);

            return $credential->refresh();
        });
    }
}
