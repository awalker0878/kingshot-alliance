<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentConfigurationCapacity;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SetRecruitmentOnboardingItemActive
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private RecruitmentConfigurationCapacity $capacity,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $itemId, bool $active): void
    {
        DB::transaction(function () use ($actorPlayerId, $allianceId, $itemId, $active): void {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::RecruitmentManage);
            $item = RecruitmentOnboardingItem::query()->where('alliance_id', $allianceId)->whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ((bool) $item->is_active === $active) {
                return;
            }
            if ($active) {
                $this->capacity->onboarding($allianceId, (string) $item->id);
            }
            $item->forceFill(['is_active' => $active, 'updated_by_player_id' => $context->actor->playerId])->save();
            $metadata = ['is_active' => $active];
            $this->audit->record('recruitment.onboarding_item.updated', $context->actor, $item, $context->alliance, $metadata);
            $this->outbox->record('recruitment.onboarding_item.updated', $allianceId, $item, $metadata);
        });
    }
}
