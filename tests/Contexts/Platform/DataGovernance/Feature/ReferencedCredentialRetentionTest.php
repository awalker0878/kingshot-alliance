<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\DataGovernance\Feature;

use App\Contexts\Platform\DataGovernance\Actions\EnforcePlatformRetention;
use App\Contexts\Platform\Integrations\Actions\ClaimExternalActorLink;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\IssueExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class ReferencedCredentialRetentionTest extends TestCase
{
    use DatabaseTruncation;

    public function test_old_link_and_receipt_references_do_not_starve_eligible_credentials_or_other_categories(): void
    {
        $f = $this->fixture();
        DB::table('external_actor_action_receipts')->insert($this->receipt($f, $f['receiptCredentialId']));
        DB::table('api_credentials')->whereIn('id', [$f['linkCredentialId'], $f['receiptCredentialId']])
            ->update(['revoked_at' => now()->subDays(120)]);
        $raw = (array) DB::table('api_credentials')->where('id', $f['receiptCredentialId'])->first();
        for ($i = 0; $i < 3; $i++) {
            DB::table('api_credentials')->insert([...$raw, 'id' => (string) Str::ulid(), 'prefix' => 'purge-'.$i, 'revoked_at' => now()->subDays(91)]);
        }
        DB::table('alliance_usage_snapshots')->insert([
            'id' => (string) Str::ulid(), 'alliance_id' => $f['allianceId'], 'active_members' => 1,
            'storage_bytes' => 0, 'active_api_credentials' => 0, 'active_webhook_subscriptions' => 0,
            'pending_outbox_messages' => 0, 'captured_at' => now()->subDays(366),
        ]);
        $history = DB::table('external_actor_action_receipts')->first();
        foreach ([1, 1, 1, 0] as $index => $expected) {
            $result = app(EnforcePlatformRetention::class)->handle(1);
            self::assertSame($expected, $result['credentialsPurged']);
            self::assertSame($index === 0 ? 1 : 0, $result['usageSnapshotsPurged']);
        }
        self::assertSame(2, DB::table('api_credentials')->count());
        self::assertSame(1, DB::table('external_actor_links')->count());
        self::assertEquals($history, DB::table('external_actor_action_receipts')->first());
    }

    public function test_an_uncommitted_foreign_key_reference_is_skipped_and_preserved_after_commit(): void
    {
        $f = $this->fixture();
        DB::table('api_credentials')->where('id', $f['receiptCredentialId'])->update(['revoked_at' => now()->subDays(120)]);
        $raw = (array) DB::table('api_credentials')->where('id', $f['receiptCredentialId'])->first();
        $eligible = (string) Str::ulid();
        DB::table('api_credentials')->insert([...$raw, 'id' => $eligible, 'prefix' => 'eligible', 'revoked_at' => now()->subDays(91)]);
        config()->set('database.connections.retention_reference', DB::connection()->getConfig());
        $other = DB::connection('retention_reference');
        DB::connection()->statement("SET lock_timeout = '150ms'");
        try {
            $other->beginTransaction();
            $other->table('external_actor_action_receipts')->insert($this->receipt($f, $f['receiptCredentialId']));
            self::assertSame(1, app(EnforcePlatformRetention::class)->handle(1)['credentialsPurged']);
            self::assertFalse(DB::table('api_credentials')->where('id', $eligible)->exists());
            self::assertTrue(DB::table('api_credentials')->where('id', $f['receiptCredentialId'])->exists());
            $other->commit();
            self::assertSame(0, app(EnforcePlatformRetention::class)->handle(1)['credentialsPurged']);
            self::assertSame(1, DB::table('external_actor_action_receipts')->count());
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::connection()->statement('SET lock_timeout = 0');
            DB::purge('retention_reference');
        }
    }

    /** @return array<string,string> */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        $linkCredential = app(CreateApiCredential::class)->handle($alliance->allianceId, $player->playerId, 'Link history', ['actor-links:write']);
        $receiptCredential = app(CreateApiCredential::class)->handle($alliance->allianceId, $player->playerId, 'Action history', ['event-participation:write']);
        $code = app(IssueExternalActorPairingCode::class)->handle($alliance->allianceId, $player->playerId, ExternalActorProvider::Discord);
        $link = app(ClaimExternalActorLink::class)->handle($alliance->allianceId, $linkCredential->credentialId, ExternalActorProvider::Discord, '123456789012345678', $code->code);

        return ['allianceId' => $alliance->allianceId, 'linkId' => $link->linkId,
            'linkCredentialId' => $linkCredential->credentialId, 'receiptCredentialId' => $receiptCredential->credentialId];
    }

    /**
     * @param  array<string,string>  $f
     * @return array<string,mixed>
     */
    private function receipt(array $f, string $credentialId): array
    {
        return ['id' => (string) Str::ulid(), 'alliance_id' => $f['allianceId'], 'external_actor_link_id' => $f['linkId'],
            'api_credential_id' => $credentialId, 'idempotency_key' => 'retained-history', 'action' => 'event.response.update',
            'request_hash' => str_repeat('a', 64), 'status' => 'succeeded', 'response' => '{"response":"going"}',
            'completed_at' => now()->subDays(121), 'created_at' => now()->subDays(121), 'updated_at' => now()->subDays(121)];
    }
}
