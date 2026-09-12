<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\DataGovernance\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\DataGovernance\Services\AllianceDataExportService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceExportBoundsTest extends TestCase
{
    use DatabaseTruncation;

    public function test_export_preserves_complete_scoped_values_redactions_counts_and_checksum_through_chunks(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $alliance = $factory->alliance($factory->player($account->userId));
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $this->createFixtureTable();
        try {
            $text = str_repeat('🌍', 18000)."\nQuoted \"value\" and \\ slash";
            for ($i = 0; $i < 61; $i++) {
                DB::table('aaa_export_test_rows')->insert([
                    'alliance_id' => $alliance->allianceId, 'body' => $i === 30 ? $text : 'Row '.$i,
                    'secret_hash' => 'private-secret', 'metadata' => '{"nested":true}', 'enabled' => $i % 2 === 0,
                ]);
            }
            DB::table('aaa_export_test_rows')->insert(['alliance_id' => 'outside-scope', 'body' => 'Never export this']);
            $maxChunkBytes = 0;
            DB::listen(static function (QueryExecuted $query) use (&$maxChunkBytes): void {
                if (str_starts_with($query->sql, 'FETCH FORWARD')) {
                    self::assertSame('FETCH FORWARD 25 FROM alliance_export_rows', $query->sql);
                    $maxChunkBytes++;
                }
            });
            $export = app(AllianceDataExportService::class)->generate(app(AccountIdentityQuery::class)->require($account->userId), $alliance->allianceId);
            $contents = stream_get_contents($export['buffer']->stream());
            self::assertIsString($contents);
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $rows = $decoded['tables']['aaa_export_test_rows'];
            self::assertCount(61, $rows);
            self::assertSame($text, $rows[30]['body']);
            self::assertSame('[REDACTED]', $rows[30]['secret_hash']);
            self::assertSame('{"nested":true}', $rows[30]['metadata']);
            self::assertTrue($rows[30]['enabled']);
            self::assertFalse($rows[31]['enabled']);
            self::assertSame(31, $rows[30]['id']);
            self::assertStringNotContainsString('private-secret', $contents);
            self::assertStringNotContainsString('Never export this', $contents);
            self::assertSame(61, $export['tableCounts']['aaa_export_test_rows']);
            self::assertSame(1 + array_sum($export['tableCounts']), $export['rowCount']);
            self::assertSame(hash('sha256', $contents), $export['sha256']);
            self::assertSame($export['sha256'], DB::table('alliance_data_exports')->value('sha256'));
            self::assertGreaterThan(2, $maxChunkBytes);
            self::assertSame(0, (int) DB::selectOne("SELECT count(*) AS count FROM pg_cursors WHERE name = 'alliance_export_rows'")->count);
        } finally {
            Schema::dropIfExists('aaa_export_test_rows');
        }
    }

    public function test_oversized_export_is_rejected_before_row_transfer_or_success_metadata(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $alliance = $factory->alliance($factory->player($account->userId));
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $this->createFixtureTable();
        try {
            // Generate the hostile size in PostgreSQL, without allocating it in this process.
            DB::statement("INSERT INTO aaa_export_test_rows (alliance_id, body) VALUES (?, repeat('x', 104857600))", [$alliance->allianceId]);
            $fetches = 0;
            DB::listen(static function (QueryExecuted $query) use (&$fetches): void {
                if (str_starts_with($query->sql, 'FETCH ')) {
                    $fetches++;
                }
            });
            try {
                app(AllianceDataExportService::class)->generate(app(AccountIdentityQuery::class)->require($account->userId), $alliance->allianceId);
                self::fail('The export exceeds its synchronous budget.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('100 MiB', $exception->getMessage());
            }
            self::assertSame(0, $fetches);
            self::assertSame(0, DB::table('alliance_data_exports')->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'platform.alliance.exported')->count());
        } finally {
            Schema::dropIfExists('aaa_export_test_rows');
        }
    }

    public function test_late_export_failure_rolls_back_success_metadata_and_can_retry(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $alliance = $factory->alliance($factory->player($account->userId));
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "alliance_data_exports"')) {
                $failed = true;
                throw new RuntimeException('Injected export failure.');
            }
        });
        try {
            app(AllianceDataExportService::class)->generate($actor, $alliance->allianceId);
            self::fail('Success metadata must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected export failure.', $exception->getMessage());
        }
        self::assertSame(0, DB::table('alliance_data_exports')->count());
        $export = app(AllianceDataExportService::class)->generate($actor, $alliance->allianceId);
        self::assertSame(1, DB::table('alliance_data_exports')->count());
        self::assertSame($export['sha256'], hash('sha256', stream_get_contents($export['buffer']->stream())));
    }

    public function test_http_download_streams_the_verified_export_and_rechecks_current_platform_access(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $alliance = $factory->alliance($factory->player($account->userId));
        $grant = app(ManagePlatformAdministrator::class)->grant($account->userId);
        $user = User::query()->findOrFail($account->userId);
        $user->forceFill(['email_verified_at' => now(),
            'two_factor_secret' => app(TotpService::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $response = $this->actingAs($user)->withSession(['accounts.recent_authentication_at' => now()->timestamp])
            ->get('/platform/alliances/'.$alliance->allianceId.'/export.json')->assertOk();
        $contents = $response->streamedContent();
        self::assertSame(hash('sha256', $contents), $response->headers->get('X-Export-SHA256'));
        self::assertSame($alliance->allianceId, json_decode($contents, true, flags: JSON_THROW_ON_ERROR)['alliance']['id']);
        DB::table('platform_administrators')->where('id', $grant)->update(['revoked_at' => now()]);
        $this->get('/platform/alliances/'.$alliance->allianceId.'/export.json')->assertForbidden();
        self::assertSame(1, DB::table('alliance_data_exports')->count());
    }

    private function createFixtureTable(): void
    {
        Schema::create('aaa_export_test_rows', static function (Blueprint $table): void {
            $table->id();
            $table->string('alliance_id');
            $table->text('body');
            $table->text('secret_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('enabled')->default(false);
        });
    }
}
