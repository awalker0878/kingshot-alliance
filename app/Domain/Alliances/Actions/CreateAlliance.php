<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Actions;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Services\AllianceRoleProvisioner;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use App\Domain\Platform\Services\AlliancePlatformDefaultsProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateAlliance
{
    public function __construct(
        private AllianceRoleProvisioner $roles,
        private AuditRecorder $audit,
        private AlliancePlatformDefaultsProvisioner $platformDefaults,
    ) {}

    public function handle(
        Player $owner,
        string $name,
        string $slug,
        string $language = 'en',
        string $timezone = 'UTC',
    ): Alliance {
        return DB::transaction(function () use ($owner, $name, $slug, $language, $timezone): Alliance {
            $lockedOwner = Player::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if ($lockedOwner->user_id === null) {
                throw ValidationException::withMessages([
                    'player' => 'An Alliance can only be created by a Player claimed by a User account.',
                ]);
            }

            if (AllianceMembership::query()
                ->where('player_id', $lockedOwner->id)
                ->where('status', MembershipStatus::Active->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'player' => 'The active Player already belongs to an Alliance.',
                ]);
            }

            $alliance = Alliance::query()->create([
                'name' => $name,
                'slug' => $slug,
                'kingdom_id' => $lockedOwner->current_kingdom_id,
                'language' => $language,
                'timezone' => $timezone,
                'status' => AllianceStatus::Active,
            ]);

            AllianceMembership::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $lockedOwner->id,
                'status' => MembershipStatus::Active,
                'rank' => AllianceRank::R5,
                'joined_at' => now(),
            ]);

            $this->roles->provision($alliance);
            $this->platformDefaults->provision($alliance);

            $this->audit->record(
                event: 'alliance.created',
                actor: $lockedOwner,
                subject: $alliance,
                alliance: $alliance,
                metadata: ['name' => $alliance->name, 'slug' => $alliance->slug],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'partition_key' => 'alliance:'.$alliance->id,
                'event_type' => 'alliance.created',
                'aggregate_type' => Alliance::class,
                'aggregate_id' => $alliance->id,
                'idempotency_key' => 'alliance.created:'.$alliance->id,
                'payload' => [
                    'alliance_id' => $alliance->id,
                    'owner_player_id' => $lockedOwner->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $alliance->refresh()->load('kingdom');
        });
    }
}
