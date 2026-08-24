<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAssistantBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_distinct_authorized_event_matches_return_ambiguity_without_evidence(): void
    {
        [$user, $actor, $alliance] = $this->governorWithAlliance(63401);
        $this->createSwordland($actor, $alliance, 'Swordland Alpha', 2);
        $this->createSwordland($actor, $alliance, 'Swordland Beta', 3);

        $response = $this->assistantRequest($user, $actor, ['question' => 'What time is Swordland?']);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ambiguous')
            ->assertJsonPath('messageKey', 'assistant.answers.eventAmbiguous')
            ->assertJsonCount(2, 'ambiguity')
            ->assertJsonCount(0, 'evidence')
            ->assertJsonCount(0, 'citations');
    }

    public function test_invalid_question_length_and_control_characters_return_bounded_validation_error(): void
    {
        [$user, $actor] = $this->governorWithAlliance(63402);

        $this->assistantRequest($user, $actor, ['question' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'validation_error')
            ->assertJsonCount(0, 'evidence')
            ->assertJsonCount(0, 'citations');

        $this->assistantRequest($user, $actor, ['question' => "What time is Swordland?\u{0001}"])
            ->assertStatus(422)
            ->assertJsonPath('status', 'validation_error');
    }

    public function test_unknown_prompt_identifier_is_rejected_instead_of_expanding_the_tool_surface(): void
    {
        [$user, $actor] = $this->governorWithAlliance(63403);

        $this->assistantRequest($user, $actor, [
            'question' => 'Localized prompt text',
            'prompt' => 'arbitrary_tool',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'validation_error')
            ->assertJsonCount(0, 'evidence');
    }

    public function test_verified_account_without_an_active_governor_cannot_use_assistant(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $this->verify($user);
        $owner = $scenario->player((int) $user->id, 63404);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $scenario->player((int) $user->id, 63405);

        $this->actingAs($user)
            ->postJson('/assistant/ask', ['question' => 'What is my next Event?'])
            ->assertStatus(409);
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

    private function createSwordland(
        PlayerReference $actor,
        AllianceReference $alliance,
        string $title,
        int $daysFromNow,
    ): void {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();

        app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays($daysFromNow)->startOfHour(),
            title: $title,
            durationMinutes: 60,
        );
    }

    /** @param array<string, string> $payload */
    private function assistantRequest(User $user, PlayerReference $actor, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withSession([$this->sessionKey() => $actor->playerId])
            ->postJson('/assistant/ask', $payload);
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
