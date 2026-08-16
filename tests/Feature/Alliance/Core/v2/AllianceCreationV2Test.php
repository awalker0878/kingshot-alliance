<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Core\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceRankPermissions;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Access\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class AllianceCreationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_claimed_player_creation_bootstraps_exact_alliance_authority_and_durable_events(): void
    {
        $scenario = (new ScenarioFactory)->claimedPlayer(4500, 'R5 Owner', 'game-4500-owner');

        $alliance = app(CreateAlliance::class)->handle(
            $scenario['player'],
            'V2 Alliance',
            'v2-alliance-4500',
            'en',
            'America/Toronto',
        );

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $scenario['player']->id)
            ->sole();

        self::assertSame($scenario['kingdom']->id, $alliance->kingdom_id);
        self::assertSame(MembershipStatus::Active, $membership->status);
        self::assertSame(AllianceRank::R5, $membership->rank);
        self::assertNotNull($membership->joined_at);
        self::assertFalse($membership->roles()->exists());
        self::assertSame(3, Role::query()->where('alliance_id', $alliance->id)->count());
        self::assertEqualsCanonicalizing([
            AlliancePermission::InvitationManage->value,
            AlliancePermission::RecruitmentManage->value,
            AlliancePermission::ContentManage->value,
        ], Permission::query()->pluck('key')->all());
        self::assertSame(
            0,
            Role::query()
                ->where('alliance_id', $alliance->id)
                ->where('key', DefaultAllianceRole::EventCoordinator->value)
                ->sole()
                ->permissions()
                ->count(),
        );

        $authorization = app(AllianceAuthorization::class);
        foreach (app(AllianceRankPermissions::class)->for(AllianceRank::R5) as $permission) {
            self::assertTrue($authorization->allows($scenario['player'], $alliance, $permission));
        }

        self::assertSame(1, DB::table('audit_events')->where([
            'alliance_id' => $alliance->id,
            'actor_player_id' => $scenario['player']->id,
            'event' => 'alliance.created',
        ])->whereNull('actor_user_id')->count());
        self::assertSame(1, DB::table('outbox_messages')->where([
            'alliance_id' => $alliance->id,
            'event_type' => 'alliance.created',
            'partition_key' => 'alliance:'.$alliance->id,
        ])->count());
        self::assertSame($scenario['player']->id, DB::table('outbox_messages')->where('event_type', 'alliance.created')->value('payload->owner_player_id'));
    }

    public function test_one_account_can_own_multiple_players_with_independent_alliance_authority_in_one_kingdom(): void
    {
        $user = User::factory()->create();
        $factory = new ScenarioFactory;
        $firstPlayer = $factory->playerFor($user, 4501, 'Alpha', 'game-4501-a')['player'];
        $secondPlayer = $factory->playerFor($user, 4501, 'Bravo', 'game-4501-b')['player'];
        $create = app(CreateAlliance::class);

        $first = $create->handle($firstPlayer, 'Alpha Alliance', 'alpha-alliance-4501');
        $second = $create->handle($secondPlayer, 'Bravo Alliance', 'bravo-alliance-4501');

        self::assertNotSame($first->id, $second->id);
        self::assertSame($first->kingdom_id, $second->kingdom_id);
        self::assertSame(1, Kingdom::query()->whereKey($first->kingdom_id)->count());
        self::assertSame(2, Player::query()->where('user_id', $user->id)->count());
        self::assertSame(2, AllianceMembership::query()
            ->whereIn('player_id', [$firstPlayer->id, $secondPlayer->id])
            ->where('status', MembershipStatus::Active->value)
            ->count());
    }

    public function test_same_player_cannot_create_a_second_active_alliance(): void
    {
        $scenario = (new ScenarioFactory)->claimedPlayer(4502, 'Single Alliance', 'game-4502-one');
        $create = app(CreateAlliance::class);
        $create->handle($scenario['player'], 'First', 'first-4502');

        $this->expectException(ValidationException::class);
        $create->handle($scenario['player'], 'Second', 'second-4502');
    }

    public function test_unclaimed_player_cannot_create_an_alliance(): void
    {
        $kingdom = app(ResolveKingdom::class)->handle(4503);
        self::assertInstanceOf(Kingdom::class, $kingdom);
        $player = app(PersistPlayerIdentity::class)->handle(
            (string) $kingdom->id,
            'Unclaimed Owner',
            'game-4503-unclaimed',
        );
        self::assertNull($player->user_id);

        $this->expectException(ValidationException::class);
        app(CreateAlliance::class)->handle($player, 'Forbidden', 'forbidden-4503');
    }
}
