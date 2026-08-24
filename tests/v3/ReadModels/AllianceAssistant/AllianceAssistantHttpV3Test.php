<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAssistantHttpV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_swordland_time_and_self_roster_answer_uses_only_event_and_self_roster_evidence(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(63101);
        $occurrence = $this->swordland($actor, $alliance, 'Swordland');
        $roster = EventRoster::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('key', 'combatants')
            ->firstOrFail();

        app(AssignEventRosterPlayer::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $occurrence->id,
            rosterId: (string) $roster->id,
            playerId: $actor->playerId,
            role: 'Rally Lead',
            slotNumber: 7,
        );

        $response = $this->assistantRequest($user, $actor, 'What time is Swordland and am I rostered?');

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'event_roster_self')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageKey', 'assistant.answers.eventTimeRostered')
            ->assertJsonPath('messageParameters.event', 'Swordland')
            ->assertJsonPath('messageParameters.role', 'Rally Lead')
            ->assertJsonPath('messageParameters.slot', 7)
            ->assertJsonCount(2, 'evidence')
            ->assertJsonCount(2, 'citations')
            ->assertJsonPath('evidence.0.sourceType', 'event')
            ->assertJsonPath('evidence.1.sourceType', 'roster')
            ->assertJsonPath('citations.0.evidenceId', 'event-'.(string) $occurrence->id)
            ->assertJsonPath('citations.1.evidenceId', 'roster-'.(string) EventRosterMember::query()->where('player_id', $actor->playerId)->value('id'));
    }

    public function test_self_roster_intent_never_returns_another_governors_assignment(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(63102);
        $otherUser = (new ScenarioFactory)->authUser();
        $this->verify($otherUser);
        $other = (new ScenarioFactory)->player((int) $otherUser->id, 63102);
        (new ScenarioFactory)->roster($actor, $alliance, $other);

        $occurrence = $this->swordland($actor, $alliance, 'Swordland');
        $roster = EventRoster::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('key', 'combatants')
            ->firstOrFail();
        app(AssignEventRosterPlayer::class)->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $occurrence->id,
            rosterId: (string) $roster->id,
            playerId: $other->playerId,
            role: 'Hidden role',
            slotNumber: 3,
        );

        $response = $this->assistantRequest($user, $actor, 'What time is Swordland and am I rostered?');

        $response
            ->assertOk()
            ->assertJsonPath('messageKey', 'assistant.answers.eventTimeNotRostered')
            ->assertJsonCount(1, 'evidence')
            ->assertJsonCount(1, 'citations')
            ->assertJsonPath('evidence.0.sourceType', 'event')
            ->assertJsonMissing(['statement' => 'Hidden role']);
    }

    public function test_another_alliances_event_never_enters_the_candidate_or_citation_set(): void
    {
        [$user, $actor] = $this->governorWithAlliance(63103);

        $hiddenScenario = new ScenarioFactory;
        $hiddenUser = $hiddenScenario->authUser();
        $this->verify($hiddenUser);
        $hiddenActor = $hiddenScenario->player((int) $hiddenUser->id, 63104);
        $hiddenAlliance = $hiddenScenario->alliance($hiddenActor);
        $hiddenScenario->roster($hiddenActor, $hiddenAlliance);
        $hiddenOccurrence = $this->swordland($hiddenActor, $hiddenAlliance, 'Swordland');

        $response = $this->assistantRequest($user, $actor, 'What time is Swordland?');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'not_found')
            ->assertJsonPath('messageKey', 'assistant.answers.eventNotFound')
            ->assertJsonCount(0, 'evidence')
            ->assertJsonCount(0, 'citations')
            ->assertJsonMissing(['sourceId' => (string) $hiddenOccurrence->id]);
    }

    public function test_write_like_question_is_unsupported_and_causes_zero_domain_mutations(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(63105);
        $this->swordland($actor, $alliance, 'Swordland');
        $before = EventRosterMember::query()->count();

        $response = $this->assistantRequest($user, $actor, 'Put me on the Swordland roster');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'unsupported')
            ->assertJsonPath('messageKey', 'assistant.answers.unsupported')
            ->assertJsonCount(0, 'evidence')
            ->assertJsonCount(0, 'citations');
        self::assertSame($before, EventRosterMember::query()->count());
    }

    public function test_assistant_answer_log_contains_metadata_but_not_question_or_answer_text(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(63106);
        $this->swordland($actor, $alliance, 'Swordland');
        $question = 'What time is Swordland?';

        Log::spy();
        $this->assistantRequest($user, $actor, $question)->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($question): bool {
                if ($message !== 'alliance_assistant.answered') {
                    return false;
                }

                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, $question)
                    && ! str_contains($encoded, 'Swordland starts')
                    && array_key_exists('intent', $context)
                    && array_key_exists('status', $context)
                    && array_key_exists('evidence_count', $context)
                    && array_key_exists('source_type_counts', $context)
                    && array_key_exists('duration_ms', $context);
            })
            ->once();
    }

    public function test_dedicated_assistant_rate_limit_is_account_scoped(): void
    {
        // Parallel test workers share the Redis throttle store while using isolated
        // databases whose numeric account IDs may overlap. Use unhashed named-limiter
        // keys in this process so this test gets a fresh bucket without weakening the
        // production limiter's account-scoped behavior.
        ThrottleRequests::shouldHashKeys(false);

        try {
            config(['assistant.rate_limit_per_minute' => 2]);
            [$user, $actor, $alliance] = $this->governorWithAlliance(63107);
            $this->swordland($actor, $alliance, 'Swordland');

            $this->assistantRequest($user, $actor, 'What time is Swordland?')->assertOk();
            $this->assistantRequest($user, $actor, 'What time is Swordland?')->assertOk();
            $this->assistantRequest($user, $actor, 'What time is Swordland?')->assertStatus(429);

            [$otherUser, $otherActor, $otherAlliance] = $this->governorWithAlliance(63108);
            $this->swordland($otherActor, $otherAlliance, 'Swordland');
            $this->assistantRequest($otherUser, $otherActor, 'What time is Swordland?')->assertOk();
        } finally {
            ThrottleRequests::shouldHashKeys();
        }
    }

    /** @return array{User, PlayerReference, AllianceReference} */
    private function governorWithAlliance(int $kingdomNumber): array
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $this->verify($user);
        $actor = $scenario->player((int) $user->id, $kingdomNumber);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        return [$user, $actor, $alliance];
    }

    private function swordland(PlayerReference $actor, AllianceReference $alliance, string $title): EventOccurrence
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2)->startOfHour(),
            title: $title,
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
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
