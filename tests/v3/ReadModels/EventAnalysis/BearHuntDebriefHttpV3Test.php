<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\EventAnalysis;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntDebriefHttpV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_governor_can_render_debrief_and_view_telemetry_is_privacy_safe(): void
    {
        $scenario = new ScenarioFactory;
        $user = $scenario->authUser();
        $this->verify($user);
        $actor = $scenario->player((int) $user->id, 61501);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $occurrence = $this->occurrence($actor, $alliance);

        Log::spy();
        $response = $this->actingAs($user)
            ->withSession([$this->sessionKey() => $actor->playerId])
            ->withHeader('X-Inertia', 'true')
            ->get('/events/'.(string) $occurrence->id.'/debrief');

        $response
            ->assertOk()
            ->assertJsonPath('component', 'Operations/Events/BearHuntDebrief')
            ->assertJsonPath('props.debrief.run.occurrenceId', (string) $occurrence->id)
            ->assertJsonPath('props.debrief.run.allianceId', $alliance->allianceId);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($actor): bool {
                if ($message !== 'bear_hunt.debrief.viewed') {
                    return false;
                }

                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, $actor->currentName)
                    && ! array_key_exists('damage', $context)
                    && ! array_key_exists('ocr', $context)
                    && array_key_exists('occurrence_id', $context)
                    && array_key_exists('alliance_id', $context)
                    && array_key_exists('governor_count', $context)
                    && array_key_exists('history_count', $context);
            })
            ->once();
    }

    public function test_outsider_cannot_open_another_alliances_bear_hunt_debrief(): void
    {
        $scenario = new ScenarioFactory;
        $ownerUser = $scenario->authUser();
        $this->verify($ownerUser);
        $owner = $scenario->player((int) $ownerUser->id, 61502);
        $alliance = $scenario->alliance($owner);
        $scenario->roster($owner, $alliance);
        $occurrence = $this->occurrence($owner, $alliance);

        $outsiderUser = $scenario->authUser();
        $this->verify($outsiderUser);
        $outsider = $scenario->player((int) $outsiderUser->id, 61503);

        $this->actingAs($outsiderUser)
            ->withSession([$this->sessionKey() => $outsider->playerId])
            ->withHeader('X-Inertia', 'true')
            ->get('/events/'.(string) $occurrence->id.'/debrief')
            ->assertForbidden();
    }

    private function occurrence(PlayerReference $actor, AllianceReference $alliance): EventOccurrence
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
            firstLocalStart: CarbonImmutable::now('UTC'),
            title: 'Bear Hunt HTTP Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
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
