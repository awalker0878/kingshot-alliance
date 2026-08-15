<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\AllianceFeatureService;
use App\Contexts\Platform\Access\Services\PlatformMutationAuthority;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ConfigureAlliancePlatform
{
    public function __construct(
        private AllianceFeatureService $features,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private PlatformMutationAuthority $mutations,
    ) {}

    public function assignPlan(User $actor, Alliance $alliance, string $planCode): void
    {
        DB::transaction(function () use ($actor, $alliance, $planCode): void {
            $context = $this->mutations->require($actor);
            $currentAlliance = Alliance::query()->whereKey($alliance->id)->sharedLock()->firstOrFail();

            $exists = DB::table('platform_plans')
                ->where('code', $planCode)
                ->where('is_active', true)
                ->sharedLock()
                ->exists();
            if (! $exists) {
                throw new InvalidArgumentException('The requested platform plan is not active.');
            }

            DB::table('alliance_plan_assignments')->upsert([[
                'alliance_id' => $currentAlliance->id,
                'plan_code' => $planCode,
                'assigned_by_user_id' => $context->actor->id,
                'assigned_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]], ['alliance_id'], [
                'plan_code',
                'assigned_by_user_id',
                'assigned_at',
                'updated_at',
            ]);

            $this->audit->record('platform.alliance.plan-assigned', $context->actor, $currentAlliance, $currentAlliance, [
                'plan_code' => $planCode,
            ]);
            $this->outbox->record('platform.alliance.plan-assigned', (string) $currentAlliance->id, $currentAlliance, [
                'alliance_id' => $currentAlliance->id,
                'plan_code' => $planCode,
            ]);
        });
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

        return DB::transaction(function () use ($actor, $alliance, $retentionDays, $queuePartition, $apiAccessEnabled, $webhooksEnabled): AlliancePlatformSetting {
            $context = $this->mutations->require($actor);
            $currentAlliance = Alliance::query()->whereKey($alliance->id)->sharedLock()->firstOrFail();

            AlliancePlatformSetting::query()->upsert([[
                'alliance_id' => $currentAlliance->id,
                'retention_days' => $retentionDays,
                'queue_partition' => $queuePartition,
                'api_access_enabled' => $apiAccessEnabled,
                'webhooks_enabled' => $webhooksEnabled,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['alliance_id'], [
                'retention_days',
                'queue_partition',
                'api_access_enabled',
                'webhooks_enabled',
                'updated_at',
            ]);

            $settings = AlliancePlatformSetting::query()->whereKey($currentAlliance->id)->firstOrFail();
            $this->audit->record('platform.alliance.settings-updated', $context->actor, $currentAlliance, $currentAlliance, [
                'retention_days' => $retentionDays,
                'queue_partition' => $queuePartition,
                'api_access_enabled' => $apiAccessEnabled,
                'webhooks_enabled' => $webhooksEnabled,
            ]);

            return $settings;
        });
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

        DB::transaction(function () use ($actor, $alliance, $featureKey, $enabled, $configuration): void {
            $context = $this->mutations->require($actor);
            $currentAlliance = Alliance::query()->whereKey($alliance->id)->sharedLock()->firstOrFail();

            $flag = $this->features->set(
                $currentAlliance,
                $context->actor,
                $featureKey,
                $enabled,
                $configuration,
            );
            $this->audit->record('platform.alliance.feature-updated', $context->actor, $flag, $currentAlliance, [
                'feature_key' => $featureKey,
                'enabled' => $enabled,
            ]);
        });
    }
}
