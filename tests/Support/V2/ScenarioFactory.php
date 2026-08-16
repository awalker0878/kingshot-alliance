<?php

declare(strict_types=1);

namespace Tests\Support\V2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use Carbon\CarbonImmutable;

final class ScenarioFactory
{
    /** @return array{user: User, kingdom: Kingdom, player: Player} */
    public function claimedPlayer(
        int $kingdomNumber = 9001,
        string $playerName = 'V2 Player',
        ?string $gamePlayerId = null,
    ): array {
        $user = User::factory()->create();

        return [
            'user' => $user,
            ...$this->playerFor($user, $kingdomNumber, $playerName, $gamePlayerId),
        ];
    }

    /** @return array{kingdom: Kingdom, player: Player} */
    public function playerFor(
        User $user,
        int $kingdomNumber,
        string $playerName,
        ?string $gamePlayerId = null,
    ): array {
        $kingdom = app(ResolveKingdom::class)->handle($kingdomNumber);

        if (! $kingdom instanceof Kingdom) {
            throw new \RuntimeException('Expected ResolveKingdom to return a Kingdom.');
        }

        $player = app(PersistPlayerIdentity::class)->handle(
            (string) $kingdom->id,
            $playerName,
            $gamePlayerId,
        );
        $player = app(ClaimPlayerAccount::class)->handle($player, $user);

        return compact('kingdom', 'player');
    }

    /** @return array{user: User, kingdom: Kingdom, player: Player, alliance: Alliance} */
    public function alliance(
        int $kingdomNumber = 9001,
        string $playerName = 'V2 Alliance Owner',
        string $allianceName = 'V2 Alliance',
        string $allianceSlug = 'v2-alliance',
    ): array {
        $scenario = $this->claimedPlayer($kingdomNumber, $playerName);
        $alliance = app(CreateAlliance::class)->handle(
            $scenario['player'],
            $allianceName,
            $allianceSlug,
        );

        return [...$scenario, 'alliance' => $alliance];
    }

    /** @return array{user: User, kingdom: Kingdom, player: Player, alliance: Alliance, event: Event} */
    public function allianceEvent(
        int $kingdomNumber = 9001,
        string $eventType = 'custom',
        string $allianceSlug = 'v2-event-alliance',
        ?CarbonImmutable $startsAt = null,
    ): array {
        $scenario = $this->alliance(
            $kingdomNumber,
            'V2 Event Owner',
            'V2 Event Alliance',
            $allianceSlug,
        );
        $type = EventType::query()->where('slug', $eventType)->sole();
        $configuration = app(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = app(CreateEvent::class)->handle(
            actor: $scenario['player'],
            configuration: $configuration,
            target: $scenario['alliance'],
            firstLocalStart: $startsAt ?? CarbonImmutable::now('UTC')->addDay()->startOfHour(),
            durationMinutes: 60,
        );

        return [...$scenario, 'event' => $event];
    }
}
