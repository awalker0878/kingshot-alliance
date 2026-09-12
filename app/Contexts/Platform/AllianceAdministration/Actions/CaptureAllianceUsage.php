<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Contexts\Platform\AllianceAdministration\Models\AllianceUsageSnapshot;
use App\Contexts\Platform\AllianceAdministration\Services\PlatformUsageService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

/** Interactive capture retains current operator authority through snapshot and audit commit. */
final readonly class CaptureAllianceUsage
{
    public function __construct(private PlatformWriteState $writeState, private PlatformAuthorization $authorization,
        private AllianceReferenceQuery $alliances, private PlatformUsageService $usage, private AuditRecorder $audit) {}

    public function handle(AccountIdentity $actor, string $allianceId): string
    {
        return DB::transaction(function () use ($actor, $allianceId): string {
            $context = $this->authorization->authorizeContext($this->writeState->lock($actor));
            $alliance = $this->alliances->lockCurrent($allianceId);
            $id = $this->usage->capture($alliance->allianceId);
            $snapshot = AllianceUsageSnapshot::query()->findOrFail($id);
            $this->audit->record('platform.alliance.usage-captured', $context->actor, $snapshot, $alliance->allianceId);

            return $id;
        });
    }
}
