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
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAssistantTelemetryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_game_fact_resolution_is_logged_without_raw_question_or_source_values(): void
    {
        [$user, $actor] = $this->governorWithAlliance(64601);
        $question = 'What generation is Amadeus?';

        Log::spy();
        $this->assistantRequest($user, $actor, $question)->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(static function (string $message, array $context) use ($question): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'alliance_assistant.answered'
                    && ($context['intent'] ?? null) === 'game_fact'
                    && ($context['game_fact_resolution'] ?? null) === 'known'
                    && ($context['handoff_kind'] ?? null) === null
                    && ! str_contains($encoded, $question)
                    && ! str_contains($encoded, 'Amadeus');
            })
            ->once();
    }

    public function test_navigation_handoff_is_logged_as_kind_only_and_does_not_log_question_or_href(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(64602);
        $this->swordland($actor, $alliance);
        $question = 'Put me on the Swordland roster';

        Log::spy();
        $this->assistantRequest($user, $actor, $question)->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(static function (string $message, array $context) use ($question): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'alliance_assistant.answered'
                    && ($context['intent'] ?? null) === 'action_handoff'
                    && ($context['handoff_kind'] ?? null) === 'navigation'
                    && ($context['game_fact_resolution'] ?? null) === null
                    && ! str_contains($encoded, $question)
                    && ! str_contains($encoded, 'Swordland')
                    && ! str_contains($encoded, '/events/');
            })
            ->once();
    }

    /** @return array{User,PlayerReference,AllianceReference} */
    private function governorWithAlliance(int $kingdomNumber): array
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $actor = $scenario->player((int) $user->id, $kingdomNumber);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);

        return [$user, $actor, $alliance];
    }

    private function swordland(PlayerReference $actor, AllianceReference $alliance): void
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();
        app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2)->startOfHour(),
            title: 'Swordland',
            durationMinutes: 60,
        );
    }

    private function assistantRequest(User $user, PlayerReference $actor, string $question)
    {
        return $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $actor->playerId])
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
}
