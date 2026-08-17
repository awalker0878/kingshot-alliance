<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Services\MembershipAdministrationGuard;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RemovePlayersFromAlliances
{
    public function __construct(
        private MembershipAdministrationGuard $guard,
        private AuditRecorder $audit,
    ) {}

    /** @param list<PlayerReference> $players */
    public function handle(array $players): void
    {
        if ($players === []) {
            return;
        }

        $playersById = [];
        foreach ($players as $player) {
            $playersById[$player->playerId] = $player;
        }

        DB::transaction(function () use ($playersById): void {
            $memberships = AllianceMembership::query()
                ->whereIn('player_id', array_keys($playersById))
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('alliance_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $allianceIds = $memberships
                ->pluck('alliance_id')
                ->map(static fn ($id): string => (string) $id)
                ->unique()
                ->values()
                ->all();

            $alliances = Alliance::query()
                ->whereIn('id', $allianceIds)
                ->orderBy('id')
                ->sharedLock()
                ->get()
                ->keyBy('id');

            foreach ($memberships as $membership) {
                $this->guard->assertCanDeactivate($membership);

                $membership->forceFill([
                    'status' => MembershipStatus::Left,
                    'left_at' => now(),
                ])->save();
                $membership->roles()->detach();

                $player = $playersById[(string) $membership->player_id] ?? null;
                $alliance = $alliances->get((string) $membership->alliance_id);

                $this->audit->record(
                    'membership.left',
                    $player,
                    $membership,
                    $alliance instanceof Alliance ? $alliance : null,
                    ['reason' => 'account-deletion'],
                );

                OutboxMessage::query()->create([
                    'alliance_id' => $membership->alliance_id,
                    'partition_key' => 'alliance:'.$membership->alliance_id,
                    'event_type' => 'membership.left',
                    'aggregate_type' => AllianceMembership::class,
                    'aggregate_id' => $membership->id,
                    'idempotency_key' => 'membership.left:'.$membership->id.':'.Str::ulid(),
                    'payload' => [
                        'alliance_id' => $membership->alliance_id,
                        'membership_id' => $membership->id,
                        'player_id' => $membership->player_id,
                        'reason' => 'account-deletion',
                    ],
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'attempts' => 0,
                ]);
            }
        });
    }
}
