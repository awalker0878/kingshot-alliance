<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentInput;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class CreateRecruitmentOnboardingItem
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $name,
        ?string $description = null,
        int $position = 0,
        bool $isRequired = true,
        bool $isActive = true,
    ): string {
        $cleanName = RecruitmentInput::requiredText($name, 'name', RecruitmentInput::LIMITS['onboardingName']);
        $description = RecruitmentInput::optionalText($description, 'description', RecruitmentInput::LIMITS['description']);
        RecruitmentInput::position($position);

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $cleanName, $description, $position, $isRequired, $isActive): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $item = RecruitmentOnboardingItem::query()->create([
                'alliance_id' => $context->alliance->id,
                'name' => $cleanName,
                'description' => $description,
                'position' => $position,
                'is_required' => $isRequired,
                'is_active' => $isActive,
                'created_by_player_id' => $context->actor->playerId,
                'updated_by_player_id' => $context->actor->playerId,
            ]);

            $this->audit->record('recruitment.onboarding_item.created', $context->actor, $item, $context->alliance, [
                'is_required' => $isRequired,
                'position' => $position,
            ]);
            $this->outbox->record('recruitment.onboarding_item.created', (string) $context->alliance->id, $item, [
                'is_required' => $isRequired,
                'position' => $position,
            ]);

            return (string) $item->id;
        });
    }
}
