<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Platform\AllianceAdministration;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\AllianceAdministration\Actions\ConfigureAlliancePlatform;
use App\Contexts\Platform\AllianceAdministration\Actions\InitializeAlliancePlatform;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AlliancePlatformInitializationConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function commitOrders(): iterable
    {
        yield 'initialization first' => [true];
        yield 'administration first' => [false];
    }

    #[DataProvider('commitOrders')]
    public function test_initialization_and_administration_serialize_without_resetting_settings(bool $initializationFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $alliance = $factory->alliance($factory->player($account->userId));
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        DB::table('alliance_platform_settings')->where('alliance_id', $alliance->allianceId)->delete();
        $initialize = static fn () => app(InitializeAlliancePlatform::class)->handle($alliance->allianceId);
        $configure = static fn () => app(ConfigureAlliancePlatform::class)->updateSettings($actor, $alliance->allianceId, 365, 'high-volume', false, false);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.platform_initializer', array_replace(DB::connection()->getConfig(), ['name' => 'platform_initializer']));
        DB::connection('platform_initializer')->statement("SET lock_timeout = '100ms'");
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $initializationFirst, $initialize, $configure, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "alliances"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('platform_initializer');
            try {
                try {
                    $initializationFirst ? $configure() : $initialize();
                    self::fail('The competing writer must wait for the Alliance owner.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $initializationFirst ? $initialize() : $configure();
            self::assertTrue($attempted);
            DB::setDefaultConnection('platform_initializer');
            $initializationFirst ? $configure() : $initialize();
            $settings = DB::table('alliance_platform_settings')->where('alliance_id', $alliance->allianceId)->first();
            self::assertNotNull($settings);
            self::assertSame(365, (int) $settings->retention_days);
            self::assertSame('high-volume', $settings->queue_partition);
            self::assertFalse((bool) $settings->api_access_enabled);
            self::assertFalse((bool) $settings->webhooks_enabled);
            self::assertSame(1, DB::table('alliance_platform_settings')->where('alliance_id', $alliance->allianceId)->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('platform_initializer');
        }
    }
}
