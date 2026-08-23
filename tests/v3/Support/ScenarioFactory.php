<?php

declare(strict_types=1);

namespace Tests\v3\Support;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Registration\Actions\RegisterUser;
use App\Contexts\Accounts\Registration\Data\RegisteredAccount;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use RuntimeException;

final class ScenarioFactory
{
    private static int $sequence = 0;

    public function account(?string $email = null): RegisteredAccount
    {
        $id = ++self::$sequence;

        return app(RegisterUser::class)->handle(
            'V3 User '.$id,
            $email ?? 'v3-user-'.$id.'@example.test',
            'Correct-Horse-Battery-Staple-V3!',
            'UTC',
        );
    }

    public function authUser(?string $email = null): User
    {
        $account = $this->account($email);

        return User::query()->findOrFail($account->userId);
    }

    public function kingdom(?int $number = null): KingdomReference
    {
        $number ??= 100000 + (++self::$sequence);

        return app(ResolveKingdom::class)->handle($number)
            ?? throw new RuntimeException('Expected a Kingdom reference.');
    }

    public function unclaimedPlayer(?int $kingdomNumber = null, ?string $stableId = null): PlayerReference
    {
        $id = ++self::$sequence;
        $kingdom = $this->kingdom($kingdomNumber);

        return app(PersistPlayerIdentity::class)->handle(
            $kingdom->kingdomId,
            'V3 Player '.$id,
            $stableId ?? 'v3-player-'.$id,
        );
    }

    public function player(int $ownerUserId, ?int $kingdomNumber = null, ?string $stableId = null): PlayerReference
    {
        $player = $this->unclaimedPlayer($kingdomNumber, $stableId);

        return app(ClaimPlayerAccount::class)->handle($player->playerId, $ownerUserId);
    }

    public function alliance(PlayerReference $owner): AllianceReference
    {
        $id = ++self::$sequence;
        $allianceId = app(CreateAlliance::class)->handle(
            $owner->playerId,
            'V3 Alliance '.$id,
            'v3-alliance-'.$id,
            'en',
            'UTC',
        );

        return app(AllianceReferenceQuery::class)->require($allianceId);
    }

    public function roster(
        PlayerReference $actor,
        AllianceReference $alliance,
        ?PlayerReference $player = null,
    ): RosterEntryReference {
        $player ??= $actor;

        return app(UpsertRosterEntry::class)->handle(
            actorPlayerId: $actor->playerId,
            allianceId: $alliance->allianceId,
            attributes: [
                'name' => $player->currentName,
                'game_player_id' => $player->gamePlayerId,
                'state' => RosterState::Active,
            ],
            expectedPlayerId: $player->playerId,
        );
    }
}
