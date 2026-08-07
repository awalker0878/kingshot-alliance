<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Recruitment\Models\RecruitmentOnboardingItem;
use App\Domain\Recruitment\Services\RecruitmentOutbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecruitmentOnboardingItem
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        string $name,
        ?string $description = null,
        int $position = 0,
        bool $isRequired = true,
        bool $isActive = true,
    ): RecruitmentOnboardingItem {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to manage recruitment onboarding.');
        }

        $cleanName = trim($name);
        if ($cleanName === '') {
            throw ValidationException::withMessages(['name' => 'An onboarding item name is required.']);
        }

        if ($position < 0 || $position > 65535) {
            throw ValidationException::withMessages(['position' => 'The onboarding item position is invalid.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $cleanName, $description, $position, $isRequired, $isActive): RecruitmentOnboardingItem {
            $item = RecruitmentOnboardingItem::query()->create([
                'alliance_id' => $alliance->id,
                'name' => $cleanName,
                'description' => $description === null ? null : trim($description),
                'position' => $position,
                'is_required' => $isRequired,
                'is_active' => $isActive,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->record('recruitment.onboarding_item.created', $actor, $item, $alliance, [
                'is_required' => $isRequired,
                'position' => $position,
            ]);
            $this->outbox->record('recruitment.onboarding_item.created', $alliance, $item, [
                'is_required' => $isRequired,
                'position' => $position,
            ]);

            return $item;
        });
    }
}
