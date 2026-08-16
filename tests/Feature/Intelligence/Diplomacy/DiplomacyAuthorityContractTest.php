<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence\Diplomacy;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Diplomacy\Actions\TransitionKingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DiplomacyAuthorityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_diplomacy_authority_is_bound_to_player_membership_not_shared_user_ownership(): void
    {
        $user = User::factory()->create();
        $kingdom = $this->kingdom(9301);
        $owner = $this->player($user, $kingdom, '9301-owner', 'Diplomacy Owner');
        $sibling = $this->player($user, $kingdom, '9301-sibling', 'Diplomacy Sibling');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Diplomacy Alliance', 'diplomacy-alliance');
        $tracking = $this->tracking($alliance->id, $kingdom, '9301-target', 'Target Alliance');
        $transition = $this->app->make(TransitionKingdomAllianceDiplomacy::class);

        $relationship = $transition->handle(
            alliance: $alliance,
            actor: $owner,
            trackingId: (string) $tracking->id,
            target: KingdomAllianceDiplomacyState::Friendly,
            attributes: ['effective_at' => '2026-09-01 00:00:00 UTC'],
        );

        self::assertSame(KingdomAllianceDiplomacyState::Friendly, $relationship->current_state);
        self::assertSame((string) $owner->id, (string) $relationship->last_transition_player_id);
        self::assertSame(1, KingdomAllianceDiplomacyTransition::query()->count());

        $this->expectException(AuthorizationException::class);
        $transition->handle(
            alliance: $alliance,
            actor: $sibling,
            trackingId: (string) $tracking->id,
            target: KingdomAllianceDiplomacyState::Ally,
            attributes: ['effective_at' => '2026-09-02 00:00:00 UTC'],
        );
    }

    public function test_diplomacy_mutation_rejects_historical_tracking_from_another_kingdom(): void
    {
        $user = User::factory()->create();
        $currentKingdom = $this->kingdom(9302);
        $historicalKingdom = $this->kingdom(9303);
        $owner = $this->player($user, $currentKingdom, '9302-owner', 'Current Diplomacy Owner');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Current Diplomacy', 'current-diplomacy');
        $tracking = $this->tracking($alliance->id, $historicalKingdom, '9303-target', 'Historical Target');

        $this->expectException(ValidationException::class);
        $this->app->make(TransitionKingdomAllianceDiplomacy::class)->handle(
            alliance: $alliance,
            actor: $owner,
            trackingId: (string) $tracking->id,
            target: KingdomAllianceDiplomacyState::Neutral,
            attributes: ['effective_at' => '2026-09-01 00:00:00 UTC'],
        );

        self::assertSame(0, KingdomAllianceDiplomacy::query()->count());
    }

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->create([
            'number' => $number,
            'status' => 'active',
        ]);
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    private function tracking(string $allianceId, Kingdom $kingdom, string $gameAllianceId, string $name): TrackedKingdomAlliance
    {
        $reference = KingdomAlliance::query()->create([
            'kingdom_id' => $kingdom->id,
            'game_alliance_id' => $gameAllianceId,
            'current_name' => $name,
            'current_tag' => 'TAG',
            'status' => KingdomAllianceStatus::Active,
        ]);

        return TrackedKingdomAlliance::query()->create([
            'alliance_id' => $allianceId,
            'kingdom_alliance_id' => $reference->id,
            'kingdom_id' => $kingdom->id,
            'state' => TrackedKingdomAllianceState::Active,
        ]);
    }
}
