<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Players\Feature;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Players\Actions\ActivatePlayer;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayerAccount;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayersFromAccount;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PlayerIdentityLifecycleExpansionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_identity_history_tracks_creation_claim_rename_stable_id_and_release(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $kingdom = $factory->kingdom(19101);
        $persist = app(PersistPlayerIdentity::class);

        $player = $persist->handle($kingdom->kingdomId, '  First Name  ', null);
        self::assertSame('First Name', $player->currentName);
        self::assertSame(1, PlayerIdentityHistory::query()->where('player_id', $player->playerId)->count());

        app(ClaimPlayerAccount::class)->handle($player->playerId, $account->userId);
        $updated = $persist->handle($kingdom->kingdomId, 'Second Name', 'stable-19101', $player->playerId);
        self::assertSame('stable-19101', $updated->gamePlayerId);
        self::assertSame('Second Name', $updated->currentName);

        app(ReleasePlayersFromAccount::class)->handle($account->userId, [$player->playerId]);
        self::assertNull(Player::query()->findOrFail($player->playerId)->user_id);
        self::assertSame(4, PlayerIdentityHistory::query()->where('player_id', $player->playerId)->count());
        self::assertSame(1, PlayerIdentityHistory::query()->where('player_id', $player->playerId)->whereNull('valid_to')->count());
        self::assertNull(PlayerIdentityHistory::query()->where('player_id', $player->playerId)->whereNull('valid_to')->firstOrFail()->user_id);
    }

    public function test_blank_name_and_stable_id_mutation_or_collision_fail_closed(): void
    {
        $factory = new ScenarioFactory;
        $kingdom = $factory->kingdom(19102);
        $persist = app(PersistPlayerIdentity::class);

        try {
            $persist->handle($kingdom->kingdomId, '   ', null);
            self::fail('Blank names must be rejected.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $first = $persist->handle($kingdom->kingdomId, 'One', 'stable-one');
        $second = $persist->handle($kingdom->kingdomId, 'Two', null);

        try {
            $persist->handle($kingdom->kingdomId, 'One', 'stable-two', $first->playerId);
            self::fail('An established stable ID cannot be replaced.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $persist->handle($kingdom->kingdomId, 'Two', 'stable-one', $second->playerId);
    }

    public function test_kingdom_move_is_blocked_by_governance_membership_and_roster_invariants(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $destination = $factory->kingdom(19110);
        $persist = app(PersistPlayerIdentity::class);

        $administrator = $factory->player($account->userId, 19111);
        $governed = $factory->player($account->userId, 19111);
        $source = $factory->kingdom(19111);
        app(BootstrapKingdomAdministrator::class)->handle($source->kingdomId, $administrator->playerId);
        $viewer = KingdomRole::query()
            ->where('kingdom_id', $source->kingdomId)
            ->where('key', DefaultKingdomRole::Viewer->value)
            ->firstOrFail();
        app(AssignKingdomRole::class)->handle($administrator->playerId, $source->kingdomId, $governed->playerId, (string) $viewer->id);

        try {
            $persist->handle($destination->kingdomId, $governed->currentName, $governed->gamePlayerId, $governed->playerId);
            self::fail('Effective governance must block a Kingdom move.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('effective Kingdom roles', $exception->getMessage());
        }

        $member = $factory->player($account->userId, 19112);
        $factory->alliance($member);
        try {
            $persist->handle($destination->kingdomId, $member->currentName, $member->gamePlayerId, $member->playerId);
            self::fail('Active membership must block a Kingdom move.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('active Alliance membership', $exception->getMessage());
        }

        $actor = $factory->player($account->userId, 19113);
        $rosterTarget = $factory->player($account->userId, 19113);
        $alliance = $factory->alliance($actor);
        $factory->roster($actor, $alliance, $rosterTarget);
        try {
            $persist->handle($destination->kingdomId, $rosterTarget->currentName, $rosterTarget->gamePlayerId, $rosterTarget->playerId);
            self::fail('Active roster state must block a Kingdom move.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('active or tracked on a roster', $exception->getMessage());
        }
    }

    public function test_voluntary_release_preserves_identity_and_is_blocked_by_live_dependencies(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $dormant = $factory->player($account->userId, 19120);

        $released = app(ReleasePlayerAccount::class)->handle($account->userId, $dormant->playerId);
        self::assertNull($released->userId);
        self::assertSame($dormant->gamePlayerId, $released->gamePlayerId);

        $member = $factory->player($account->userId, 19121);
        $factory->alliance($member);
        $this->expectException(ValidationException::class);
        app(ReleasePlayerAccount::class)->handle($account->userId, $member->playerId);
    }

    public function test_activation_records_context_change_audit(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $first = $factory->player($account->userId, 19130);
        $second = $factory->player($account->userId, 19130);

        app(ActivatePlayer::class)->handle($account->userId, $second->playerId, $first->playerId);

        $audit = AuditEvent::query()->where('event', 'player.context_changed')->latest('created_at')->firstOrFail();
        self::assertSame($second->playerId, (string) $audit->subject_id);
        self::assertSame($first->playerId, $audit->metadata['previous_player_id']);
        self::assertSame($second->playerId, $audit->metadata['player_id']);
    }
}
