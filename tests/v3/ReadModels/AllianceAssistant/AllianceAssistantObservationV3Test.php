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
use App\Contexts\Intelligence\Observations\Actions\RecordKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAssistantObservationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_observation_is_returned_as_observation_not_game_fact(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(63301);
        $observationId = $this->recordObservation($actor, $alliance, 'Northern Watch', 'NW', '99887766', 82);

        $response = $this->assistantRequest($user, $actor, 'What have we observed about Northern Watch?');

        $response
            ->assertOk()
            ->assertJsonPath('intent', 'alliance_observation')
            ->assertJsonPath('status', 'answered')
            ->assertJsonPath('messageKey', 'assistant.answers.observationFound')
            ->assertJsonPath('classifications.0', 'observation')
            ->assertJsonCount(1, 'evidence')
            ->assertJsonCount(1, 'citations')
            ->assertJsonPath('evidence.0.sourceType', 'observation')
            ->assertJsonPath('evidence.0.sourceId', $observationId)
            ->assertJsonPath('evidence.0.classification', 'observation')
            ->assertJsonPath('citations.0.evidenceId', 'observation-'.$observationId)
            ->assertJsonMissing(['classification' => 'game_fact']);
    }

    public function test_other_alliance_observation_is_indistinguishable_from_missing_authorized_data(): void
    {
        [$user, $actor] = $this->governorWithAlliance(63302);

        $hiddenScenario = new ScenarioFactory;
        $hiddenUser = $hiddenScenario->authUser();
        $this->verify($hiddenUser);
        $hiddenActor = $hiddenScenario->player((int) $hiddenUser->id, 63303);
        $hiddenAlliance = $hiddenScenario->alliance($hiddenActor);
        $hiddenScenario->roster($hiddenActor, $hiddenAlliance);
        $hiddenObservationId = $this->recordObservation($hiddenActor, $hiddenAlliance, 'Hidden Watch', 'HID', '11223344', 70);

        $response = $this->assistantRequest($user, $actor, 'What have we observed about Hidden Watch?');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'not_found')
            ->assertJsonPath('messageKey', 'assistant.answers.observationNotFound')
            ->assertJsonCount(0, 'evidence')
            ->assertJsonCount(0, 'citations')
            ->assertJsonMissing(['sourceId' => $hiddenObservationId]);
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

    private function recordObservation(
        PlayerReference $actor,
        AllianceReference $alliance,
        string $name,
        string $tag,
        string $power,
        int $memberCount,
    ): string {
        $trackingId = app(StartTrackingKingdomAlliance::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'current_name' => $name,
                'current_tag' => $tag,
                'game_alliance_id' => 'fixture-'.$tag.'-'.$actor->playerId,
            ],
        );

        return app(RecordKingdomAllianceObservation::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            $trackingId,
            [
                'observed_name' => $name,
                'observed_tag' => $tag,
                'power' => $power,
                'member_count' => $memberCount,
                'captured_at' => now()->subMinute()->toIso8601String(),
            ],
        );
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
