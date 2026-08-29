<?php

declare(strict_types=1);

namespace Tests\v3\Acceptance;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class CapabilitySurfaceHttpMatrixV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_every_surface_exposes_the_expected_rank_and_inertia_payload_matrix(): void
    {
        [$alliance, $actors] = $this->allianceWithRankMatrix(79101, 'matrix');
        $owner = $actors[AllianceRank::R5->value]['player'];
        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $owner->playerId)
            ->firstOrFail();
        $event = $this->bearHunt($owner, $alliance);
        $candidate = $this->candidate($alliance->allianceId, $owner->playerId);
        $trackingId = $this->tracking($owner, $alliance, 'Acceptance Watch');

        foreach ($actors as $rank => $identity) {
            $officer = in_array($rank, [AllianceRank::R5->value, AllianceRank::R4->value], true);
            $canRecruit = $rank === AllianceRank::R5->value;

            $eventResponse = $this->getAs($identity['user'], $identity['player'], route('events.management', $event));
            if ($officer) {
                $eventResponse->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
                    ->component('Operations/Events/Manage')
                    ->where('event.id', (string) $event->id)
                    ->has('eventCommand.sections')
                    ->has('rallyBuilder', 1));
            } else {
                $eventResponse->assertForbidden();
            }

            $this->getAs($identity['user'], $identity['player'], route('alliance.roster.history', $entry))
                ->assertOk()
                ->assertInertia(static fn (Assert $page): Assert => $page
                    ->component('Intelligence/Roster/History')
                    ->where('entry.id', (string) $entry->id)
                    ->where('canManage', $officer)
                    ->where('capabilityProfile.eventAccess', 'available')
                    ->where('capabilityProfile.evidence.access', $officer ? 'available' : 'unavailable'));

            $candidateResponse = $this->getAs(
                $identity['user'],
                $identity['player'],
                route('alliance.recruitment.candidates.show', $candidate),
            );
            if ($canRecruit) {
                $candidateResponse->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
                    ->component('Alliance/Recruitment/Candidate')
                    ->where('candidate.id', (string) $candidate->id)
                    ->where('transferCampaign.playerLink', 'linked')
                    ->where('transferCampaign.available', true));
            } else {
                $candidateResponse->assertForbidden();
            }

            $this->getAs(
                $identity['user'],
                $identity['player'],
                route('alliance.kingdom-alliances.history', $trackingId),
            )->assertOk()->assertInertia(static fn (Assert $page): Assert => $page
                ->component('Intelligence/KingdomWatch/History')
                ->where('canManage', $officer)
                ->where('freshness', 'missing')
                ->has('timeline', 0));

            $this->getAs($identity['user'], $identity['player'], route('dashboard'))
                ->assertOk()
                ->assertInertia(static fn (Assert $page): Assert => $page
                    ->component('Dashboard/Home')
                    ->where('membership.rank', $rank)
                    ->where('overview.allianceCommand', static fn (mixed $command): bool => $officer
                        ? is_array($command)
                        : $command === null)
                    ->has('overview.officerBriefs', $officer ? 3 : 0));

            $this->getAs($identity['user'], $identity['player'], route('assistant.index'))
                ->assertOk()
                ->assertInertia(static fn (Assert $page): Assert => $page
                    ->component('Assistant/Index')
                    ->where('alliance.name', $alliance->name)
                    ->where('maxQuestionLength', 500));
        }
    }

    public function test_http_boundaries_reject_cross_account_alliance_and_kingdom_identifiers(): void
    {
        [$alliance, $actors] = $this->allianceWithRankMatrix(79102, 'boundary-source');
        $owner = $actors[AllianceRank::R5->value]['player'];
        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $owner->playerId)
            ->firstOrFail();
        $event = $this->bearHunt($owner, $alliance);
        $candidate = $this->candidate($alliance->allianceId, $owner->playerId);
        $trackingId = $this->tracking($owner, $alliance, 'Boundary Watch');

        [$foreignAlliance, $foreignActors] = $this->allianceWithRankMatrix(79103, 'boundary-foreign');
        $foreign = $foreignActors[AllianceRank::R5->value];

        $this->getAs($foreign['user'], $foreign['player'], route('events.management', $event))
            ->assertForbidden();
        $this->getAs($foreign['user'], $foreign['player'], route('alliance.roster.history', $entry))
            ->assertNotFound();
        $this->getAs($foreign['user'], $foreign['player'], route('alliance.recruitment.candidates.show', $candidate))
            ->assertNotFound();
        $this->getAs($foreign['user'], $foreign['player'], route('alliance.kingdom-alliances.history', $trackingId))
            ->assertNotFound();

        $sourceUser = $actors[AllianceRank::R5->value]['user'];
        $this->actingAs($sourceUser)
            ->withSession([$this->sessionKey() => $foreign['player']->playerId])
            ->get(route('dashboard'))
            ->assertForbidden();

        $foreignDashboard = $this->getAs($foreign['user'], $foreign['player'], route('dashboard'));
        $foreignDashboard->assertOk();
        self::assertStringNotContainsString($alliance->allianceId, $foreignDashboard->getContent());
        self::assertStringContainsString($foreignAlliance->allianceId, $foreignDashboard->getContent());
    }

    public function test_member_history_caps_the_http_payload_and_declares_more_rows(): void
    {
        [$alliance, $actors] = $this->allianceWithRankMatrix(79104, 'bounded-history');
        $owner = $actors[AllianceRank::R5->value];
        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $owner['player']->playerId)
            ->firstOrFail();
        foreach (range(1, 251) as $index) {
            PlayerSnapshot::query()->create([
                'alliance_id' => $alliance->allianceId,
                'roster_entry_id' => (string) $entry->id,
                'player_id' => $owner['player']->playerId,
                'actor_player_id' => $owner['player']->playerId,
                'observed_name' => 'Bounded Governor',
                'power' => 100000000 + $index,
                'captured_at' => now()->subMinutes(252 - $index),
                'source' => 'manual',
                'idempotency_key' => 'bounded-member-history-'.$index,
            ]);
        }

        $this->getAs($owner['user'], $owner['player'], route('alliance.roster.history', $entry))
            ->assertOk()
            ->assertInertia(static fn (Assert $page): Assert => $page
                ->component('Intelligence/Roster/History')
                ->has('snapshots', 250)
                ->where('hasMoreSnapshots', true));
    }

    /**
     * @return array{AllianceReference,array<string,array{user:User,player:PlayerReference}>}
     */
    private function allianceWithRankMatrix(int $kingdom, string $prefix): array
    {
        $scenario = new ScenarioFactory;
        $ownerUser = $scenario->authUser($prefix.'-r5@example.test');
        $this->verify($ownerUser);
        $owner = $scenario->player((int) $ownerUser->id, $kingdom);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $actors = [AllianceRank::R5->value => ['user' => $ownerUser, 'player' => $owner]];

        foreach ([AllianceRank::R4, AllianceRank::R3, AllianceRank::R1] as $rank) {
            $user = $scenario->authUser($prefix.'-'.$rank->value.'@example.test');
            $this->verify($user);
            $player = $scenario->player((int) $user->id, $kingdom);
            AllianceMembership::query()->create([
                'alliance_id' => $alliance->allianceId,
                'player_id' => $player->playerId,
                'status' => MembershipStatus::Active,
                'rank' => $rank,
                'joined_at' => now(),
            ]);
            $scenario->roster($owner, $alliance, $player);
            $actors[$rank->value] = ['user' => $user, 'player' => $player];
        }

        return [$alliance, $actors];
    }

    private function bearHunt(PlayerReference $actor, AllianceReference $alliance): Event
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2),
            title: 'Acceptance Bear Hunt',
            durationMinutes: 60,
        );

        return Event::query()->with('occurrences')->findOrFail($created->eventId);
    }

    private function candidate(string $allianceId, string $playerId): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $playerId,
            'full_name' => 'Acceptance Candidate',
            'email' => 'acceptance-candidate@example.test',
            'source' => 'acceptance',
            'stage' => RecruitmentStage::Screening,
            'submitted_at' => now()->subDay(),
        ]);
    }

    private function tracking(PlayerReference $actor, AllianceReference $alliance, string $name): string
    {
        return app(StartTrackingKingdomAlliance::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'current_name' => $name,
                'current_tag' => 'ACPT',
                'game_alliance_id' => strtolower(str_replace(' ', '-', $name)),
            ],
        );
    }

    private function getAs(User $user, PlayerReference $player, string $uri): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([$this->sessionKey() => $player->playerId])
            ->get($uri);
    }

    private function verify(User $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function sessionKey(): string
    {
        return (string) config('game_world.active_player_session_key');
    }
}
