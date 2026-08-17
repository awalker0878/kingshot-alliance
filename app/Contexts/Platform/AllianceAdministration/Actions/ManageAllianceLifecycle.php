<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Actions\TransitionAllianceLifecycle;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\DataGovernance\Services\LegalHoldService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageAllianceLifecycle
{
    public function __construct(
        private LegalHoldService $legalHolds,
        private PlatformWriteState $platformWriteState,
        private PlatformAuthorization $mutations,
        private AllianceReferenceQuery $alliances,
        private TransitionAllianceLifecycle $transitionAlliance,
    ) {}

    public function suspend(AccountIdentity $actor, string $allianceId, string $reason): AllianceReference
    {
        return $this->transition($actor, $allianceId, AllianceStatus::Suspended, $reason);
    }

    public function close(AccountIdentity $actor, string $allianceId, string $reason): AllianceReference
    {
        return $this->transition($actor, $allianceId, AllianceStatus::Closed, $reason);
    }

    public function markDeleted(AccountIdentity $actor, string $allianceId, string $reason): AllianceReference
    {
        return $this->transition($actor, $allianceId, AllianceStatus::Deleted, $reason);
    }

    public function restore(AccountIdentity $actor, string $allianceId, string $reason): AllianceReference
    {
        return $this->transition($actor, $allianceId, AllianceStatus::Active, $reason);
    }

    private function transition(
        AccountIdentity $actor,
        string $allianceId,
        AllianceStatus $target,
        string $reason,
    ): AllianceReference {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A lifecycle reason is required.');
        }

        return DB::transaction(function () use ($actor, $allianceId, $target, $reason): AllianceReference {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $currentAlliance = $this->alliances->lockCurrent($allianceId);

            if ($target === AllianceStatus::Deleted && $this->legalHolds->active('alliance', $currentAlliance->allianceId)) {
                throw new InvalidArgumentException('This alliance is protected by an active legal hold.');
            }

            $retentionUntil = $target === AllianceStatus::Closed
                ? $this->retentionUntil($currentAlliance->allianceId)
                : null;

            return $this->transitionAlliance->handle(
                actor: $context->actor,
                allianceId: $currentAlliance->allianceId,
                target: $target,
                reason: $reason,
                retentionUntil: $retentionUntil,
            );
        });
    }

    private function retentionUntil(string $allianceId): CarbonInterface
    {
        $settings = AlliancePlatformSetting::query()->whereKey($allianceId)->lockForUpdate()->first();
        $retentionDays = $settings instanceof AlliancePlatformSetting ? (int) $settings->retention_days : 30;

        return now()->addDays(max(1, min(3650, $retentionDays)));
    }
}
