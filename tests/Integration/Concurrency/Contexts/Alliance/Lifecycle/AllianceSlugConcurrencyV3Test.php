<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Lifecycle;

use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Actions\UpdateAllianceSettings;
use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceSlugConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool,bool,bool}> */
    public static function competingMutations(): iterable
    {
        yield 'create against create' => [false, false, true];
        yield 'create against update' => [false, true, true];
        yield 'update against create' => [true, false, true];
        yield 'update against update' => [true, true, true];
        yield 'independent updates' => [true, true, false];
    }

    #[DataProvider('competingMutations')]
    public function test_concurrent_slug_claims_preserve_the_winner_and_caller_transaction(bool $update, bool $otherUpdate, bool $sameSlug): void
    {
        $actor = $this->actor($update);
        $other = $this->actor($otherUpdate);
        $before = $this->state();
        $original = $actor['allianceId'] === null ? null : Alliance::query()->findOrFail($actor['allianceId'])->toJson();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.slug_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'slug_competitor']));
        DB::connection('slug_competitor')->statement("SET lock_timeout = '100ms'");
        $competed = false;
        $winnerId = null;
        $compete = function () use ($other, $otherUpdate, $sameSlug, $primary, &$competed, &$winnerId): void {
            if ($competed) {
                return;
            }
            $competed = true;
            DB::setDefaultConnection('slug_competitor');
            try {
                $winnerId = $this->apply($other, $otherUpdate, $sameSlug ? 'contended-url' : 'independent-url', 'Winning Alliance');
            } finally {
                DB::setDefaultConnection($primary);
            }
        };
        if ($update) {
            Alliance::updating(static function (Alliance $alliance) use ($actor, $compete): void {
                if ((string) $alliance->id === $actor['allianceId'] && $alliance->slug === 'contended-url') {
                    $compete();
                }
            });
        } else {
            DB::listen(static function (QueryExecuted $query) use ($primary, $compete): void {
                if ($query->connectionName === $primary && str_starts_with($query->sql, 'select * from "alliances"') && in_array('contended-url', $query->bindings, true)) {
                    $compete();
                }
            });
        }

        try {
            DB::transaction(function () use ($actor, $update, $sameSlug): void {
                try {
                    $this->apply($actor, $update, 'contended-url', 'Primary Alliance');
                    self::assertFalse($sameSlug, 'A competing URL winner must produce field feedback.');
                } catch (ValidationException $exception) {
                    self::assertTrue($sameSlug);
                    self::assertArrayHasKey('slug', $exception->errors());
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
            });
            self::assertTrue($competed);
            self::assertNotNull($winnerId);
            self::assertSame('Winning Alliance', Alliance::query()->findOrFail($winnerId)->name);
            self::assertSame(1, Alliance::query()->where('slug', 'contended-url')->count());
            if ($sameSlug && $actor['allianceId'] !== null) {
                self::assertSame($original, Alliance::query()->findOrFail($actor['allianceId'])->toJson());
            }
            self::assertSame($before['audit'] + ($sameSlug ? 1 : 2), DB::table('audit_events')->count());
            self::assertSame($before['outbox'] + ($sameSlug ? 1 : 2), DB::table('outbox_messages')->count());
            if ($sameSlug) {
                $afterWinner = $this->state();
                try {
                    $this->apply($actor, $update, 'contended-url', 'Retried Alliance');
                    self::fail('Retrying the losing URL claim must preserve the winner.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('slug', $exception->errors());
                }
                self::assertSame($afterWinner, $this->state());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('slug_competitor');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function mutations(): iterable
    {
        yield 'creation' => [false];
        yield 'settings update' => [true];
    }

    #[DataProvider('mutations')]
    public function test_unrelated_unique_failures_propagate_without_partial_writes(bool $update): void
    {
        $actor = $this->actor($update);
        $other = $this->actor(true);
        $before = $this->state();
        $breakPrimaryKey = static function (Alliance $alliance) use ($other): void {
            if ($alliance->slug === 'unclaimed-url') {
                $alliance->id = $other['allianceId'];
            }
        };
        $update ? Alliance::updating($breakPrimaryKey) : Alliance::creating($breakPrimaryKey);
        try {
            $this->apply($actor, $update, 'unclaimed-url', 'Rejected Alliance');
            self::fail('An unrelated database failure must propagate.');
        } catch (UniqueConstraintViolationException $exception) {
            self::assertSame('23505', $exception->errorInfo[0] ?? null);
        }
        self::assertSame($before, $this->state());
    }

    #[DataProvider('mutations')]
    public function test_late_audit_failure_rolls_back_settings_and_all_bootstrap_records(bool $update): void
    {
        $actor = $this->actor($update);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($update, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array($update ? 'alliance.settings_changed' : 'alliance.created', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected Alliance audit failure.');
            }
        });
        try {
            $this->apply($actor, $update, 'rolled-back-url', 'Rolled Back Alliance');
            self::fail('All owner writes must roll back.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Alliance audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{userId:int,playerId:string,allianceId:?string} */
    private function actor(bool $existingAlliance): array
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59260);

        return ['userId' => $account->userId, 'playerId' => $player->playerId, 'allianceId' => $existingAlliance ? $factory->alliance($player)->allianceId : null];
    }

    /** @param array{userId:int,playerId:string,allianceId:?string} $actor */
    private function apply(array $actor, bool $update, string $slug, string $name): string
    {
        return $update
            ? app(UpdateAllianceSettings::class)->handle((string) $actor['allianceId'], $actor['playerId'], $name, $slug, SupportedAllianceLocale::English, 'UTC')
            : app(CreateAlliance::class)->handle($actor['userId'], $actor['playerId'], $name, $slug);
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return [
            'alliances' => DB::table('alliances')->orderBy('id')->get()->toJson(),
            'memberships' => DB::table('alliance_memberships')->orderBy('id')->get()->toJson(),
            'roles' => DB::table('roles')->orderBy('id')->get()->toJson(),
            'plans' => DB::table('alliance_plan_assignments')->orderBy('alliance_id')->get()->toJson(),
            'settings' => DB::table('alliance_platform_settings')->orderBy('alliance_id')->get()->toJson(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
