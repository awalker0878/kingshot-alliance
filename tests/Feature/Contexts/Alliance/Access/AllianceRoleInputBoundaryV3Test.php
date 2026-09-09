<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Alliance\Access;

use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\UpdateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceRoleInputBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function invalidPermissions(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['permissions' => null]];
        yield 'object' => [['permissions' => ['selected' => AlliancePermission::ContentManage->value]]];
        yield 'unknown' => [['permissions' => ['unowned.permission']]];
        yield 'duplicate' => [['permissions' => [AlliancePermission::ContentManage->value, AlliancePermission::ContentManage->value]]];
    }

    /** @param array<string,mixed> $payload */
    #[DataProvider('invalidPermissions')]
    public function test_malformed_permission_payloads_fail_without_creating_or_clearing_authority(array $payload): void
    {
        $s = $this->scenario();
        $before = $this->snapshot();
        $this->postJson('/alliance/roles', ['name' => 'New role', ...$payload])->assertUnprocessable();
        $this->patchJson('/alliance/roles/'.$s['roleId'], ['name' => 'Changed role', ...$payload])->assertUnprocessable();
        self::assertSame($before, $this->snapshot());
    }

    public function test_explicit_empty_permissions_are_valid_for_creation_and_revocation(): void
    {
        $s = $this->scenario();
        $this->post('/alliance/roles', ['name' => 'Empty role', 'permissions' => []])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/alliance/roles/'.$s['roleId'], ['name' => 'Cleared role', 'permissions' => []])
            ->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame(0, Role::query()->findOrFail($s['roleId'])->permissions()->count());
        self::assertSame('Cleared role', Role::query()->findOrFail($s['roleId'])->name);
    }

    public function test_creation_rejects_a_name_that_would_overflow_the_stable_key(): void
    {
        $s = $this->scenario();
        $before = $this->snapshot();
        $this->postJson('/alliance/roles', ['name' => str_repeat('a', 65), 'permissions' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        try {
            app(CreateAllianceRole::class)->handle($s['allianceId'], $s['playerId'], str_repeat('b', 65), []);
            self::fail('Direct owner calls must respect stable-key storage bounds.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('name', $exception->errors());
        }
        self::assertSame($before, $this->snapshot());
    }

    public function test_owner_name_bounds_preserve_existing_role_state(): void
    {
        $s = $this->scenario();
        $before = $this->snapshot();
        try {
            app(UpdateAllianceRole::class)->handle($s['allianceId'], $s['playerId'], $s['roleId'], str_repeat('c', 101), []);
            self::fail('Direct owner calls must respect display-name storage bounds.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('name', $exception->errors());
        }
        self::assertSame($before, $this->snapshot());
    }

    /** @return array{allianceId:string,playerId:string,roleId:string} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $player = $factory->player((int) $user->id, 59251);
        $alliance = $factory->alliance($player);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $player->playerId, 'Content role', [AlliancePermission::ContentManage]);
        $allianceFacts = app(PlayerIdentityContextQuery::class)->forPlayers([$player->playerId])[$player->playerId] ?? null;
        $kingdom = app(KingdomAuthorityFactsQuery::class)->findCurrent($player->playerId, $player->kingdomId)?->permissionKeysObservedAtRead ?? [];
        $version = app(PlayerAuthorityContextVersion::class)->issue($player, $allianceFacts, $kingdom);
        $this->actingAs($user)->withSession([
            (string) config('game_world.active_player_session_key') => $player->playerId,
            'accounts.recent_authentication_at' => now()->timestamp,
        ])->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version);

        return ['allianceId' => $alliance->allianceId, 'playerId' => $player->playerId, 'roleId' => $roleId];
    }

    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        return [
            'roles' => DB::table('roles')->orderBy('id')->get()->toJson(),
            'permissions' => DB::table('role_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toJson(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
