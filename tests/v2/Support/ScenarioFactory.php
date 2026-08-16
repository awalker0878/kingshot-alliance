<?php

declare(strict_types=1);

namespace Tests\v2\Support;

use App\Contexts\Accounts\Actions\RegisterUser;
use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use RuntimeException;

final class ScenarioFactory
{
    private static int $sequence = 0;

    public function user(?string $email = null): User
    {
        $id = ++self::$sequence;

        return app(RegisterUser::class)->handle(
            'V2 User '.$id,
            $email ?? 'v2-user-'.$id.'@example.test',
            'Correct-Horse-Battery-Staple-'.$id.'!',
            'UTC',
        );
    }

    public function kingdom(?int $number = null): Kingdom
    {
        $number ??= 100000 + (++self::$sequence);
        $kingdom = app(ResolveKingdom::class)->handle($number);

        return $kingdom ?? throw new RuntimeException('Expected a Kingdom.');
    }

    public function unclaimedPlayer(?int $kingdomNumber = null, ?string $stableId = null): Player
    {
        $id = ++self::$sequence;
        $kingdom = $this->kingdom($kingdomNumber);

        return app(PersistPlayerIdentity::class)->handle(
            (string) $kingdom->id,
            'V2 Player '.$id,
            $stableId ?? 'v2-player-'.$id,
        );
    }

    public function player(User $owner, ?int $kingdomNumber = null, ?string $stableId = null): Player
    {
        $player = $this->unclaimedPlayer($kingdomNumber, $stableId);

        return app(ClaimPlayerAccount::class)->handle($player, $owner);
    }

    public function alliance(Player $owner): Alliance
    {
        $id = ++self::$sequence;

        return app(CreateAlliance::class)->handle(
            $owner,
            'V2 Alliance '.$id,
            'v2-alliance-'.$id,
            'en',
            'UTC',
        );
    }
}
