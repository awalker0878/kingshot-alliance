<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Alliance\Lifecycle;

use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Actions\UpdateAllianceSettings;
use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceSettingsInputBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string,string,string,string}> */
    public static function invalidSettings(): iterable
    {
        yield 'blank name' => ['', 'valid-url', 'UTC', 'name'];
        yield 'oversized name' => [str_repeat('n', 121), 'valid-url', 'UTC', 'name'];
        yield 'blank slug' => ['Valid name', '', 'UTC', 'slug'];
        yield 'oversized slug' => ['Valid name', str_repeat('s', 121), 'UTC', 'slug'];
        yield 'expanded slug' => ['Valid name', str_repeat('Æ', 61), 'UTC', 'slug'];
        yield 'reserved slug' => ['Valid name', 'Admin', 'UTC', 'slug'];
        yield 'invalid timezone' => ['Valid name', 'valid-url', 'not/a-timezone', 'timezone'];
    }

    #[DataProvider('invalidSettings')]
    public function test_creation_and_update_share_owner_validation_without_partial_writes(string $name, string $slug, string $timezone, string $field): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->authUser();
        $player = $factory->player((int) $account->id, 59259);
        $alliance = $factory->alliance($player);
        $before = $this->state();
        foreach (['create', 'update'] as $operation) {
            try {
                if ($operation === 'create') {
                    app(CreateAlliance::class)->handle((int) $account->id, $player->playerId, $name, $slug, 'en', $timezone);
                } else {
                    app(UpdateAllianceSettings::class)->handle($alliance->allianceId, $player->playerId, $name, $slug, SupportedAllianceLocale::English, $timezone);
                }
                self::fail('Invalid settings must be rejected by the owner.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($field, $exception->errors());
            }
            self::assertSame($before, $this->state());
        }
    }

    public function test_creation_accepts_storage_boundaries_and_update_of_the_same_values_is_a_no_op(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->authUser();
        $player = $factory->player((int) $account->id, 59259);
        $name = str_repeat('N', 120);
        $slug = str_repeat('s', 120);
        $id = app(CreateAlliance::class)->handle((int) $account->id, $player->playerId, ' '.$name.' ', $slug, 'fr', 'America/Toronto');
        $record = Alliance::query()->findOrFail($id);
        self::assertSame($name, $record->name);
        self::assertSame($slug, $record->slug);
        self::assertSame('fr', $record->language);
        self::assertSame('America/Toronto', $record->timezone);
        $before = $this->state();
        app(UpdateAllianceSettings::class)->handle($id, $player->playerId, $name, $slug, SupportedAllianceLocale::French, 'America/Toronto');
        self::assertSame($before, $this->state());
    }

    public function test_http_and_direct_creation_reject_unsupported_language_and_reserved_urls(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->authUser();
        $account->forceFill(['email_verified_at' => now()])->save();
        $player = $factory->player((int) $account->id, 59259);
        $kingdom = app(KingdomAuthorityFactsQuery::class)->findCurrent($player->playerId, $player->kingdomId)?->permissionKeysObservedAtRead ?? [];
        $version = app(PlayerAuthorityContextVersion::class)->issue($player, null, $kingdom);
        $this->actingAs($account)->withSession([
            (string) config('game_world.active_player_session_key') => $player->playerId,
        ])->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version);
        $before = $this->state();
        foreach ([['slug' => 'admin'], ['language' => 'unsupported']] as $invalid) {
            $this->postJson('/alliances', $invalid + ['name' => 'New Alliance', 'slug' => 'new-alliance', 'language' => 'en', 'timezone' => 'UTC'])
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
            self::assertSame($before, $this->state());
        }
        try {
            app(CreateAlliance::class)->handle((int) $account->id, $player->playerId, 'New Alliance', 'new-alliance', 'unsupported');
            self::fail('Direct creation must use the same supported-language vocabulary.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('language', $exception->errors());
        }
        self::assertSame($before, $this->state());
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return [
            'alliances' => DB::table('alliances')->orderBy('id')->get()->toJson(),
            'memberships' => DB::table('alliance_memberships')->orderBy('id')->get()->toJson(),
            'roles' => DB::table('roles')->count(),
            'plans' => DB::table('alliance_plan_assignments')->count(),
            'settings' => DB::table('alliance_platform_settings')->count(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
