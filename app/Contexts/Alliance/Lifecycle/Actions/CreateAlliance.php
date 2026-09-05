<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Actions;

use App\Contexts\Alliance\Access\Services\AllianceRoleProvisioner;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\Services\AllianceBootstrapProvisioner;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateAlliance
{
    public function __construct(
        private AllianceRoleProvisioner $roles,
        private AuditRecorder $audit,
        private AllianceBootstrapProvisioner $platformDefaults,
        private PlayerReferenceQuery $players,
    ) {}

    public function handle(string $ownerPlayerId, string $name, string $slug, string $language = 'en', string $timezone = 'UTC'): string
    {
        return DB::transaction(function () use ($ownerPlayerId, $name, $slug, $language, $timezone): string {
            $owner = $this->players->require($ownerPlayerId);
            if (! $owner->claimed()) {
                throw ValidationException::withMessages(['player' => 'An Alliance can only be created by a Player claimed by a User account.']);
            }

            if (AllianceMembership::query()->where('player_id', $ownerPlayerId)->where('status', MembershipStatus::Active->value)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['player' => 'The active Player already belongs to an Alliance.']);
            }

            $alliance = Alliance::query()->create([
                'name' => $name,
                'slug' => $slug,
                'kingdom_id' => $owner->kingdomId,
                'language' => $language,
                'timezone' => $timezone,
                'status' => AllianceStatus::Active,
            ]);
            AllianceMembership::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $ownerPlayerId,
                'status' => MembershipStatus::Active,
                'rank' => AllianceRank::R5,
                'joined_at' => now(),
            ]);

            // Provision specialist roles, including Gift Code Coordinator, without
            // assigning coverage authority by rank or implicitly to the creator.
            $this->roles->provision($alliance);
            $this->platformDefaults->provision($alliance);
            $this->audit->record('alliance.created', $owner, $alliance, $alliance, ['name' => $alliance->name, 'slug' => $alliance->slug]);
            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'partition_key' => 'alliance:'.$alliance->id,
                'event_type' => 'alliance.created',
                'aggregate_type' => Alliance::class,
                'aggregate_id' => $alliance->id,
                'idempotency_key' => 'alliance.created:'.$alliance->id,
                'payload' => ['alliance_id' => $alliance->id, 'owner_player_id' => $ownerPlayerId],
                'occurred_at' => now(), 'available_at' => now(), 'attempts' => 0,
            ]);

            return (string) $alliance->id;
        });
    }
}
