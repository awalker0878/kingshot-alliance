<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Actions;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Services\AllianceRoleProvisioner;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Services\AlliancePlatformDefaultsProvisioner;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CreateAlliance
{
    public function __construct(
        private AllianceRoleProvisioner $roles,
        private AuditRecorder $audit,
        private AlliancePlatformDefaultsProvisioner $platformDefaults,
    ) {}

    public function handle(
        User $owner,
        string $name,
        string $slug,
        ?string $kingdom = null,
        string $language = 'en',
        string $timezone = 'UTC',
    ): Alliance {
        return DB::transaction(function () use ($owner, $name, $slug, $kingdom, $language, $timezone): Alliance {
            $alliance = Alliance::query()->create([
                'name' => $name,
                'slug' => $slug,
                'kingdom' => $kingdom,
                'language' => $language,
                'timezone' => $timezone,
                'status' => AllianceStatus::Active,
                'created_by_user_id' => $owner->id,
            ]);

            $membership = AllianceMembership::query()->create([
                'alliance_id' => $alliance->id,
                'user_id' => $owner->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $roles = $this->roles->provision($alliance);
            $ownerRole = $roles[DefaultAllianceRole::Owner->value] ?? null;

            if ($ownerRole === null) {
                throw new RuntimeException('The default owner role was not provisioned.');
            }

            $membership->roles()->attach($ownerRole->id, [
                'alliance_id' => $alliance->id,
            ]);
            $this->platformDefaults->provision($alliance, $owner);

            $this->audit->record(
                event: 'alliance.created',
                actor: $owner,
                subject: $alliance,
                alliance: $alliance,
                metadata: [
                    'name' => $alliance->name,
                    'slug' => $alliance->slug,
                ],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'alliance.created',
                'aggregate_type' => Alliance::class,
                'aggregate_id' => $alliance->id,
                'idempotency_key' => 'alliance.created:'.$alliance->id,
                'payload' => [
                    'alliance_id' => $alliance->id,
                    'owner_user_id' => $owner->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $alliance->refresh();
        });
    }
}
