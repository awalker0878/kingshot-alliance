<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlanAssignment;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\AllianceAdministration\Services\AllianceFeatureService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ConfigureAlliancePlatform
{
    public function __construct(
        private AllianceFeatureService $features,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private PlatformWriteState $platformWriteState,
        private PlatformAuthorization $mutations,
        private AllianceReferenceQuery $alliances,
    ) {}

    public function assignPlan(AccountIdentity $actor, string $allianceId, string $planCode): void
    {
        DB::transaction(function () use ($actor, $allianceId, $planCode): void {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $alliance = $this->alliances->lockCurrent($allianceId);

            if (! DB::table('platform_plans')->where('code', $planCode)->where('is_active', true)->sharedLock()->exists()) {
                throw new InvalidArgumentException('The requested platform plan is not active.');
            }

            AlliancePlanAssignment::query()->upsert([[
                'alliance_id' => $alliance->allianceId,
                'plan_code' => $planCode,
                'assigned_by_user_id' => $context->actor->userId,
                'assigned_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]], ['alliance_id'], ['plan_code', 'assigned_by_user_id', 'assigned_at', 'updated_at']);

            $assignment = AlliancePlanAssignment::query()->whereKey($alliance->allianceId)->firstOrFail();
            $this->audit->record(
                'platform.alliance.plan-assigned',
                $context->actor,
                $assignment,
                $alliance->allianceId,
                ['plan_code' => $planCode],
            );
            $this->outbox->record(
                'platform.alliance.plan-assigned',
                $alliance->allianceId,
                $assignment,
                ['alliance_id' => $alliance->allianceId, 'plan_code' => $planCode],
            );
        });
    }

    public function updateSettings(
        AccountIdentity $actor,
        string $allianceId,
        int $retentionDays,
        string $queuePartition,
        bool $apiAccessEnabled,
        bool $webhooksEnabled,
    ): void {
        if ($retentionDays < 1 || $retentionDays > 3650) {
            throw new InvalidArgumentException('Retention must be between 1 and 3650 days.');
        }
        if (! in_array($queuePartition, ['standard', 'high-volume', 'maintenance-sensitive'], true)) {
            throw new InvalidArgumentException('Unsupported queue partition.');
        }

        DB::transaction(function () use (
            $actor,
            $allianceId,
            $retentionDays,
            $queuePartition,
            $apiAccessEnabled,
            $webhooksEnabled,
        ): void {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $alliance = $this->alliances->lockCurrent($allianceId);

            AlliancePlatformSetting::query()->upsert([[
                'alliance_id' => $alliance->allianceId,
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

            $settings = AlliancePlatformSetting::query()->whereKey($alliance->allianceId)->firstOrFail();
            $this->audit->record(
                'platform.alliance.settings-updated',
                $context->actor,
                $settings,
                $alliance->allianceId,
                [
                    'retention_days' => $retentionDays,
                    'queue_partition' => $queuePartition,
                    'api_access_enabled' => $apiAccessEnabled,
                    'webhooks_enabled' => $webhooksEnabled,
                ],
            );
        });
    }

    /** @param array<string, mixed>|null $configuration */
    public function setFeature(
        AccountIdentity $actor,
        string $allianceId,
        string $featureKey,
        bool $enabled,
        ?array $configuration = null,
    ): void {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/', $featureKey) !== 1) {
            throw new InvalidArgumentException('Feature key is invalid.');
        }

        DB::transaction(function () use ($actor, $allianceId, $featureKey, $enabled, $configuration): void {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $alliance = $this->alliances->lockCurrent($allianceId);
            $flag = $this->features->set($alliance->allianceId, $context->actor, $featureKey, $enabled, $configuration);
            $this->audit->record(
                'platform.alliance.feature-updated',
                $context->actor,
                $flag,
                $alliance->allianceId,
                ['feature_key' => $featureKey, 'enabled' => $enabled],
            );
        });
    }
}
