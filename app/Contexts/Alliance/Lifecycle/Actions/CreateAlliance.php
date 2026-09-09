<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Access\Services\AllianceRoleProvisioner;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\Services\AllianceBootstrapProvisioner;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceSettingsInput;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateAlliance
{
    public function __construct(
        private AllianceRoleProvisioner $roles,
        private AuditRecorder $audit,
        private AllianceBootstrapProvisioner $platformDefaults,
        private PlayerReferenceQuery $players,
        private AccountIdentityQuery $accounts,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    public function handle(int $actorUserId, string $ownerPlayerId, string $name, string $slug, string $language = 'en', string $timezone = 'UTC'): string
    {
        $settings = AllianceSettingsInput::from($name, $slug, $language, $timezone);

        return DB::transaction(function () use ($actorUserId, $ownerPlayerId, $settings): string {
            $this->accounts->lockActive($actorUserId);
            $candidate = $this->players->require($ownerPlayerId);
            if ($candidate->userId !== $actorUserId || $candidate->canonicalPlayerId !== null) {
                throw ValidationException::withMessages(['player' => 'Select a current Player owned by this account.']);
            }
            // Kingdom precedes Player, matching identity persistence. Account
            // serialization also coordinates release, reconciliation and deletion.
            try {
                $this->kingdoms->lockActiveShared($candidate->kingdomId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages(['kingdom' => 'The selected Kingdom is archived or unavailable.']);
            }
            $owner = $this->players->lockCurrent($ownerPlayerId);
            if ($owner->userId !== $actorUserId || $owner->kingdomId !== $candidate->kingdomId) {
                throw ValidationException::withMessages(['player' => 'Player ownership or Kingdom changed. Reload before creating an Alliance.']);
            }

            if (AllianceMembership::query()->where('player_id', $ownerPlayerId)->where('status', MembershipStatus::Active->value)->exists()) {
                throw ValidationException::withMessages(['player' => 'The active Player already belongs to an Alliance.']);
            }

            $alliance = Alliance::query()->firstOrCreate(['slug' => $settings->slug], [
                ...$settings->attributes(),
                'kingdom_id' => $owner->kingdomId,
                'status' => AllianceStatus::Active,
            ]);
            if (! $alliance->wasRecentlyCreated) {
                throw ValidationException::withMessages(['slug' => 'This Alliance URL name is already in use.']);
            }
            try {
                DB::transaction(static fn () => AllianceMembership::query()->create([
                    'alliance_id' => $alliance->id,
                    'player_id' => $ownerPlayerId,
                    'status' => MembershipStatus::Active,
                    'rank' => AllianceRank::R5,
                    'joined_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                if (! AllianceMembership::query()->where('player_id', $ownerPlayerId)->where('status', MembershipStatus::Active->value)->exists()) {
                    throw $exception;
                }
                throw ValidationException::withMessages(['player' => 'The active Player already belongs to an Alliance.']);
            }

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
