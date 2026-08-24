<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Enums\EventResponseSource;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\TerritoryPlanning\Actions\AttachTerritoryPlanRevisionToEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAssistantGameWorldHttpV3Test extends TestCase
{
    use RefreshDatabase;

    private const MAP_DATASET = 'kingshot-community-observed-2026-08-21-v1';

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24T16:00:00Z'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_participation_returns_only_self_state_with_event_and_participation_citations(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(64601);
        $occurrence = $this->event($actor, $alliance, 'swordland-showdown', 'Swordland', 2);
        $actorResponse = $this->response($occurrence, $actor, EventResponseChoice::Going);

        $scenario = new ScenarioFactory;
        $otherUser = $scenario->authUser();
        $other = $scenario->player((int) $otherUser->id, 64601);
        $scenario->roster($actor, $alliance, $other);
        $otherResponse = $this->response($occurrence, $other, EventResponseChoice::Unavailable);

        $response = $this->assistantRequest($user, $actor, 'What did I RSVP for this week?');

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'event_participation_self')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageParameters.count', 1)
            ->assertJsonPath('messageParameters.items.0.event', 'Swordland')
            ->assertJsonPath('messageParameters.items.0.response', 'going')
            ->assertJsonPath('evidence.0.sourceType', 'event')
            ->assertJsonPath('evidence.1.sourceType', 'participation');

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        self::assertStringContainsString((string) $actorResponse->id, $encoded);
        self::assertStringNotContainsString((string) $otherResponse->id, $encoded);
        self::assertStringNotContainsString('unavailable', $encoded);
    }

    public function test_battle_plan_returns_only_the_active_governors_assignment(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(64602);
        $occurrence = $this->event($actor, $alliance, 'swordland-showdown', 'Swordland', 2);
        $objective = $this->objective($occurrence, $actor, 'Hold north tower');
        $assignment = EventObjectiveAssignment::query()->create([
            'objective_id' => (string) $objective->id,
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $actor->playerId,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now(),
            'notes' => 'Rotate after first rally.',
        ]);

        $scenario = new ScenarioFactory;
        $otherUser = $scenario->authUser();
        $other = $scenario->player((int) $otherUser->id, 64602);
        $scenario->roster($actor, $alliance, $other);
        $hiddenObjective = $this->objective($occurrence, $actor, 'Hidden south objective');
        $hiddenAssignment = EventObjectiveAssignment::query()->create([
            'objective_id' => (string) $hiddenObjective->id,
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $other->playerId,
            'assigned_by_player_id' => $actor->playerId,
            'assigned_at' => now()->addSecond(),
            'notes' => 'Hidden note',
        ]);

        $response = $this->assistantRequest($user, $actor, 'What is my Swordland assignment?');

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'battle_plan_self')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageParameters.count', 1)
            ->assertJsonPath('messageParameters.items.0.objective', 'Hold north tower')
            ->assertJsonPath('evidence.1.sourceType', 'battle_plan_assignment')
            ->assertJsonPath('evidence.1.sourceId', (string) $assignment->id);

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString((string) $hiddenAssignment->id, $encoded);
        self::assertStringNotContainsString('Hidden south objective', $encoded);
        self::assertStringNotContainsString('Hidden note', $encoded);
    }

    public function test_unknown_academy_fact_is_an_answered_cited_unknown_source_state(): void
    {
        [$user, $actor] = $this->governorWithAlliance(64603);

        $response = $this->assistantRequest(
            $user,
            $actor,
            'What does Academy research Fortified Mail VI level 1 do?',
        );

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'game_fact')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageKey', 'assistant.answers.gameFactUnknown')
            ->assertJsonPath('messageParameters.resolution', 'unknown')
            ->assertJsonPath('evidence.0.classification', 'game_fact')
            ->assertJsonPath('evidence.0.metadata.resolution', 'unknown')
            ->assertJsonPath('evidence.0.metadata.evidenceStatus', 'source_table_missing')
            ->assertJsonPath('citations.0.metadata.evidenceStatus', 'source_table_missing');
    }

    public function test_transfer_status_keeps_owner_unmet_and_unknown_states_and_never_expands_target_scope(): void
    {
        [$user, $actor, $alliance, $roster] = $this->governorWithAlliance(64610);
        $target = 64611;
        $this->transferParticipant($actor, $alliance, $roster, 64610, $target);

        $response = $this->assistantRequest($user, $actor, 'Can I transfer to Kingdom 64611?');
        $response
            ->assertOk()
            ->assertJsonPath('intent', 'transfer_status_self')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageParameters.targetKingdomNumber', $target)
            ->assertJsonPath('evidence.0.sourceType', 'transfer_assessment');

        $requirements = $response->json('messageParameters.requirements');
        $unmet = $response->json('messageParameters.unmet');
        self::assertIsArray($requirements);
        self::assertIsArray($unmet);
        self::assertContains('transfer_group', array_column($requirements, 'key'));
        self::assertContains('unmet', array_column($requirements, 'state'));
        self::assertContains('transfer_group', array_column($unmet, 'key'));
        self::assertContains('unmet', array_column($unmet, 'state'));

        (new ScenarioFactory)->kingdom(64612);
        $outside = $this->assistantRequest($user, $actor, 'Can I transfer to Kingdom 64612?');
        $outside
            ->assertOk()
            ->assertJsonPath('status', 'not_found')
            ->assertJsonPath('messageKey', 'assistant.answers.transferNotInScope')
            ->assertJsonCount(0, 'evidence')
            ->assertJsonCount(0, 'citations');
    }

    public function test_territory_plan_returns_the_event_attached_immutable_published_revision(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(64620);
        $occurrence = $this->event($actor, $alliance, 'bear-hunt', 'Bear Hunt', 1);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Bear Hive Alpha',
            self::MAP_DATASET,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $this->layers($alliance),
            [],
            [$this->city(100)],
        );
        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);
        app(AttachTerritoryPlanRevisionToEvent::class)->handle(
            $actor->playerId,
            (string) $occurrence->id,
            $published->publishedRevisionId,
            'positioning',
        );

        app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            $this->layers($alliance),
            [],
            [$this->city(250)],
        );

        $response = $this->assistantRequest($user, $actor, 'Which hive layout are we using for Bear Hunt?');

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'territory_plan')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageParameters.planName', 'Bear Hive Alpha')
            ->assertJsonPath('evidence.1.sourceType', 'territory_plan_revision')
            ->assertJsonPath('evidence.1.sourceId', $published->publishedRevisionId)
            ->assertJsonPath('evidence.1.classification', 'alliance_strategy')
            ->assertJsonPath('evidence.1.metadata.mapDatasetId', self::MAP_DATASET);
        self::assertNotEmpty($response->json('evidence.1.metadata.mapDatasetChecksum'));
    }

    /** @return array{User,PlayerReference,AllianceReference,RosterEntryReference} */
    private function governorWithAlliance(int $kingdomNumber): array
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $this->verify($user);
        $actor = $scenario->player((int) $user->id, $kingdomNumber);
        $alliance = $scenario->alliance($actor);
        $roster = $scenario->roster($actor, $alliance);

        return [$user, $actor, $alliance, $roster];
    }

    private function event(
        PlayerReference $actor,
        AllianceReference $alliance,
        string $slug,
        string $title,
        int $daysFromNow,
    ): EventOccurrence {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', $slug))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays($daysFromNow)->startOfHour(),
            title: $title,
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->with('event')->findOrFail($created->firstOccurrenceId);
    }

    private function response(
        EventOccurrence $occurrence,
        PlayerReference $player,
        EventResponseChoice $choice,
    ): EventResponse {
        return EventResponse::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'player_id' => $player->playerId,
            'response' => $choice->value,
            'source' => EventResponseSource::Self->value,
            'responded_by_player_id' => $player->playerId,
            'responded_at' => now(),
        ]);
    }

    private function objective(
        EventOccurrence $occurrence,
        PlayerReference $actor,
        string $name,
    ): EventObjective {
        return EventObjective::query()->create([
            'occurrence_id' => (string) $occurrence->id,
            'objective_type' => 'position',
            'name' => $name,
            'description' => $name.' description',
            'priority' => 1,
            'status' => EventObjectiveStatus::Planned->value,
            'sort_order' => 1,
            'created_by_player_id' => $actor->playerId,
            'updated_by_player_id' => $actor->playerId,
        ]);
    }

    private function transferParticipant(
        PlayerReference $actor,
        AllianceReference $alliance,
        RosterEntryReference $roster,
        int $homeNumber,
        int $targetNumber,
    ): void {
        $scenario = new ScenarioFactory;
        $scenario->kingdom($targetNumber);
        $scenario->kingdom($homeNumber + 100);
        $scenario->kingdom($targetNumber + 100);

        $now = CarbonImmutable::now('UTC');
        $windowId = app(SaveTransferWindow::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'label' => 'Assistant transfer window',
                'pre_transfer_starts_at' => $now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $now->subDay()->toIso8601String(),
                'transfer_opens_at' => $now->addDay()->toIso8601String(),
                'ends_at' => $now->addDays(3)->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Assistant transfer fixture',
                'observed_at' => $now->subDays(4)->toIso8601String(),
            ],
        );
        app(CreateTransferPlan::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            ['label' => 'Assistant transfer plan', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('transfer_window_id', $windowId)
            ->firstOrFail();
        app(SaveTransferParticipant::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            (string) $plan->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $roster->rosterEntryId,
                'destination_kingdom' => $targetNumber,
            ],
        );

        app(SaveTransferGroup::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            $windowId,
            $this->groupData('Home group', [$homeNumber, $homeNumber + 100]),
        );
        app(SaveTransferGroup::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            $windowId,
            $this->groupData('Target group', [$targetNumber, $targetNumber + 100]),
        );
    }

    /** @param list<int> $kingdomNumbers */
    private function groupData(string $label, array $kingdomNumbers): array
    {
        return [
            'official_label' => $label,
            'kingdom_numbers' => $kingdomNumbers,
            'source_type' => TransferSourceType::InGame,
            'source_reference' => 'KingShot Transfer Group screen',
            'observed_at' => CarbonImmutable::now('UTC')->subMinutes(30)->toIso8601String(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function layers(AllianceReference $alliance): array
    {
        return [[
            'key' => 'owner',
            'alliance_id' => $alliance->allianceId,
            'external_name' => null,
            'display_name' => $alliance->name,
            'presentation_color' => '#4da3ff',
        ]];
    }

    /** @return array<string,mixed> */
    private function city(int $coordinate): array
    {
        return [
            'key' => 'assistant-http-city-'.$coordinate,
            'alliance_key' => 'owner',
            'group_key' => null,
            'type' => 'governor_city',
            'player_id' => null,
            'external_player_name' => 'Assistant Governor',
            'label' => 'Assistant Governor',
            'x' => $coordinate,
            'y' => $coordinate,
            'rotation' => 0,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }

    private function assistantRequest(User $user, PlayerReference $actor, string $question): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([$this->sessionKey() => $actor->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $this->versionFor($actor))
            ->postJson('/assistant/ask', ['question' => $question]);
    }

    private function versionFor(PlayerReference $player): string
    {
        $alliance = app(PlayerIdentityContextQuery::class)
            ->forPlayers([$player->playerId])[$player->playerId] ?? null;
        $kingdomPermissions = app(KingdomAuthorityFactsQuery::class)
            ->findCurrent($player->playerId, $player->kingdomId)
            ?->permissionKeysObservedAtRead ?? [];

        return app(PlayerAuthorityContextVersion::class)->issue(
            $player,
            $alliance,
            $kingdomPermissions,
        );
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
