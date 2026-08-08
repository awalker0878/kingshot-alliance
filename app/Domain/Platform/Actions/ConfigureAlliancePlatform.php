<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\AllianceFeatureService;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ConfigureAlliancePlatform
{
    public function __construct(
        private AllianceFeatureService $features,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function assignPlan(User $actor, Alliance $alliance, string $planCode): void
    {
        $exists = DB::table('platform_plans')
            ->where('code', $planCode)
            ->where('is_active', true)
            ->exists();
        if (! $exists) {
            throw new InvalidArgumentException('The requested platform plan is not active.');
        }

        DB::table('alliance_plan_assignments')->updateOrInsert(
            ['alliance_id' => $alliance->id],
            [
                'plan_code' => $planCode,
                'assigned_by_user_id' => $actor->id,
                'assigned_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->audit->record('platform.alliance.plan-assigned', $actor, $alliance, $alliance, [
            'plan_code' => $planCode,
        ]);
        $this->outbox->record('platform.alliance.plan-assigned', (string) $alliance->id, $alliance, [
            'alliance_id' => $alliance->id,
            'plan_code' => $planCode,
        ]);
    }

    public function updateSettings(
        User $actor,
        Alliance $alliance,
        int $retentionDays,
        string $queuePartition,
        bool $apiAccessEnabled,
        bool $webhooksEnabled,
    ): AlliancePlatformSetting {
        if ($retentionDays < 1 || $retentionDays > 3650) {
            throw new InvalidArgumentException('Retention must be between 1 and 3650 days.');
        }
        if (! in_array($queuePartition, ['standard', 'high-volume', 'maintenance-sensitive'], true)) {
            throw new InvalidArgumentException('Unsupported queue partition.');
        }

        $settings = AlliancePlatformSetting::query()->updateOrCreate(
            ['alliance_id' => $alliance->id],
            [
                'retention_days' => $retentionDays,
                'queue_partition' => $queuePartition,
                'api_access_enabled' => $apiAccessEnabled,
                'webhooks_enabled' => $webhooksEnabled,
            ],
        );

        $this->audit->record('platform.alliance.settings-updated', $actor, $alliance, $alliance, [
            'retention_days' => $retentionDays,
            'queue_partition' => $queuePartition,
            'api_access_enabled' => $apiAccessEnabled,
            'webhooks_enabled' => $webhooksEnabled,
        ]);

        return $settings;
    }

    /** @param array<string, mixed>|null $configuration */
    public function setFeature(
        User $actor,
        Alliance $alliance,
        string $featureKey,
        bool $enabled,
        ?array $configuration = null,
    ): void {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/', $featureKey) !== 1) {
            throw new InvalidArgumentException('Feature key is invalid.');
        }

        $flag = $this->features->set($alliance, $actor, $featureKey, $enabled, $configuration);
        $this->audit->record('platform.alliance.feature-updated', $actor, $flag, $alliance, [
            'feature_key' => $featureKey,
            'enabled' => $enabled,
        ]);
    }
}
