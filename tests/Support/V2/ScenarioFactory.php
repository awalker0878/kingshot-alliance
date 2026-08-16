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
}
