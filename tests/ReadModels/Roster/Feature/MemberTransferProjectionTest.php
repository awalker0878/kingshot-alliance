<?php

declare(strict_types=1);

namespace Tests\ReadModels\Roster\Feature;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\Roster\Queries\MemberCapabilityProfileQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class MemberTransferProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_hydrates_only_its_target_even_when_the_plan_is_large(): void
    {
        $f = $this->fixture();
        $this->addUnrelatedParticipants($f, 150);
        $hydrated = [];
        Event::listen('eloquent.retrieved: '.TransferParticipant::class, static function (TransferParticipant $row) use (&$hydrated): void {
            $hydrated[] = (string) $row->id;
        });

        $profile = app(MemberCapabilityProfileQuery::class)->forPlayer($f['actor']->playerId, $f['alliance']->allianceId, $f['entry'], $f['actor']);

        self::assertSame([(string) $f['participant']->id], $hydrated, 'A member profile must not hydrate another participant to find its target.');
        self::assertSame((string) $f['participant']->id, $profile['transfer']['assessment']['participantId']);
        self::assertSame('outgoing', $profile['transfer']['assessment']['direction']);
        $evaluation = app(TransferEligibilityQuery::class)->forPlan($f['alliance']->allianceId, $f['plan'], collect([$f['participant']]))[(string) $f['participant']->id]['assessment'];
        self::assertSame($evaluation->outcome->value, $profile['transfer']['assessment']['outcome']);
        self::assertSame(array_map(static fn ($requirement): string => $requirement->key->value, $evaluation->requirements), array_column($profile['transfer']['assessment']['requirements'], 'key'));
    }

    public function test_missing_or_withdrawn_target_never_falls_back_to_another_participant(): void
    {
        $f = $this->fixture();
        $this->addUnrelatedParticipants($f, 30);
        $f['participant']->update(['withdrawn_at' => now()]);
        $hydrated = 0;
        Event::listen('eloquent.retrieved: '.TransferParticipant::class, static function () use (&$hydrated): void {
            $hydrated++;
        });

        $profile = app(MemberCapabilityProfileQuery::class)->forPlayer($f['actor']->playerId, $f['alliance']->allianceId, $f['entry'], $f['actor']);

        self::assertSame(['access' => 'available', 'assessment' => null], $profile['transfer']);
        self::assertSame(0, $hydrated);
    }

    public function test_target_lookup_requires_matching_alliance_plan_and_player_without_eager_relations(): void
    {
        $f = $this->fixture();
        $query = app(TransferParticipantQuery::class);
        $row = $query->activeForPlayer($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, $f['actor']->playerId);
        self::assertInstanceOf(TransferParticipant::class, $row);
        self::assertSame((string) $f['participant']->id, (string) $row->id);
        self::assertSame([], $row->getRelations());
        self::assertNull($query->activeForPlayer($f['actor']->playerId, $f['alliance']->allianceId, (string) Str::ulid(), $f['actor']->playerId));
        self::assertNull($query->activeForPlayer($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, (string) Str::ulid()));
        $f['participant']->update(['withdrawn_at' => now()]);
        self::assertNull($query->activeForPlayer($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, $f['actor']->playerId));
    }

    public function test_target_lookup_rechecks_revoked_membership_before_reading_participants(): void
    {
        $f = $this->fixture();
        $query = app(TransferParticipantQuery::class);
        self::assertNotNull($query->activeForPlayer($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, $f['actor']->playerId));
        AllianceMembership::query()->where('alliance_id', $f['alliance']->allianceId)->where('player_id', $f['actor']->playerId)->update(['status' => MembershipStatus::Suspended->value]);
        $hydrated = [];
        Event::listen('eloquent.retrieved: '.TransferParticipant::class, static function (TransferParticipant $row) use (&$hydrated): void {
            $hydrated[] = (string) $row->id;
        });

        try {
            $query->activeForPlayer($f['actor']->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, $f['actor']->playerId);
            self::fail('Current membership must be checked again for each read.');
        } catch (AuthorizationException) {
            self::assertSame([], $hydrated);
        }
    }

    public function test_an_officer_from_another_alliance_cannot_read_the_plan(): void
    {
        $f = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $other = $factory->player($factory->account()->userId, 79903);
        $factory->alliance($other);

        $this->expectException(AuthorizationException::class);
        app(TransferParticipantQuery::class)->activeForPlayer($other->playerId, $f['alliance']->allianceId, (string) $f['plan']->id, $f['actor']->playerId);
    }

    /** @return array{actor:PlayerReference,alliance:AllianceReference,entry:AllianceRosterEntry,plan:TransferPlan,participant:TransferParticipant} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player($factory->account()->userId, 79901);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance);
        $entry = AllianceRosterEntry::query()->findOrFail($roster->rosterEntryId);
        $factory->kingdom(79902);
        $window = app(SaveTransferWindow::class)->handle($alliance->allianceId, $actor->playerId, [
            'label' => 'Member transfer bounds',
            'pre_transfer_starts_at' => now()->subDays(3)->toIso8601String(),
            'invitational_starts_at' => now()->subDays(2)->toIso8601String(),
            'transfer_opens_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
            'source_type' => TransferSourceType::OfficialPublication,
            'source_reference' => 'Fixture window',
            'observed_at' => now()->subDays(4)->toIso8601String(),
        ]);
        app(CreateTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, ['label' => 'Member plan', 'transfer_window_id' => $window]);
        $plan = TransferPlan::query()->where('alliance_id', $alliance->allianceId)->sole();
        app(SaveTransferParticipant::class)->handle($alliance->allianceId, $actor->playerId, (string) $plan->id, ['direction' => TransferDirection::Outgoing, 'roster_entry_id' => $roster->rosterEntryId, 'destination_kingdom' => 79902]);
        $participant = TransferParticipant::query()->where('transfer_plan_id', $plan->id)->sole();

        return compact('actor', 'alliance', 'entry', 'plan', 'participant');
    }

    /** @param array{actor:PlayerReference,alliance:AllianceReference,entry:AllianceRosterEntry,plan:TransferPlan,participant:TransferParticipant} $f */
    private function addUnrelatedParticipants(array $f, int $count): void
    {
        $players = [];
        $participants = [];
        for ($i = 0; $i < $count; $i++) {
            $playerId = strtolower((string) Str::ulid());
            $players[] = ['id' => $playerId, 'current_kingdom_id' => $f['actor']->kingdomId, 'current_name' => 'Unrelated '.$i, 'created_at' => now(), 'updated_at' => now()];
            $participants[] = [
                'id' => strtolower((string) Str::ulid()), 'alliance_id' => $f['alliance']->allianceId,
                'transfer_plan_id' => (string) $f['plan']->id, 'player_id' => $playerId,
                'direction' => 'staying', 'observed_name' => 'Unrelated '.$i,
                'source_kingdom_id' => $f['actor']->kingdomId, 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table((new Player)->getTable())->insert($players);
        DB::table('transfer_participants')->insert($participants);
    }
}
