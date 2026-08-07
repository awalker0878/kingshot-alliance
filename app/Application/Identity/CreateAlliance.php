<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Enums\AllianceStatus;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\AuditEvent;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CreateAlliance
{
    public function __construct(private AllianceRoleProvisioner $roles) {}

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

            AuditEvent::query()->create([
                'alliance_id' => $alliance->id,
                'actor_user_id' => $owner->id,
                'event' => 'alliance.created',
                'subject_type' => Alliance::class,
                'subject_id' => $alliance->id,
                'metadata' => [
                    'name' => $alliance->name,
                    'slug' => $alliance->slug,
                ],
                'created_at' => now(),
            ]);

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
