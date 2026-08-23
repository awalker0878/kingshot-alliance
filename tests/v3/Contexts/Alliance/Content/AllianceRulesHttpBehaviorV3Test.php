<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\SaveAllianceRules;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceRulesHttpBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_active_member_can_read_rules_but_cannot_update_them_over_http(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $ownerUser = $scenarios->authUser();
        $ownerUser->forceFill(['email_verified_at' => now()])->save();
        $owner = $scenarios->player((int) $ownerUser->id, 720101);
        $alliance = $scenarios->alliance($owner);

        app(SaveAllianceRules::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            "1. Join Bear Hunt rallies on time.\n2. Follow leadership calls.",
            'en',
        );

        $memberUser = $scenarios->authUser();
        $memberUser->forceFill(['email_verified_at' => now()])->save();
        $member = $scenarios->player((int) $memberUser->id, 720101);

        AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $this->actingAs($memberUser)
            ->withSession([(string) config('game_world.active_player_session_key') => $member->playerId])
            ->withHeader('X-Inertia', 'true')
            ->get('/alliance/rules')
            ->assertOk()
            ->assertJsonPath('component', 'Alliance/Rules/Index')
            ->assertJsonPath('props.canManageContent', false)
            ->assertJsonPath('props.rules.body', "1. Join Bear Hunt rallies on time.\n2. Follow leadership calls.");

        $this->actingAs($memberUser)
            ->withSession([
                (string) config('game_world.active_player_session_key') => $member->playerId,
                'auth.password_confirmed_at' => time(),
            ])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $this->versionFor($member))
            ->put('/alliance/rules', [
                'body' => 'An R1 member must not replace Alliance Rules.',
                'locale' => 'en',
            ])
            ->assertForbidden();

        self::assertSame(
            "1. Join Bear Hunt rallies on time.\n2. Follow leadership calls.",
            ContentItem::query()
                ->where('alliance_id', $alliance->allianceId)
                ->where('slug', ContentItem::ALLIANCE_RULES_SLUG)
                ->firstOrFail()
                ->body,
        );
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
