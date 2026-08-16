<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence\Roster;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Actions\RecordPlayerSnapshot;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Contexts\Intelligence\Roster\Services\RosterCsvParser;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class RosterCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_classifies_stable_updates_creates_and_name_ambiguity_without_persisting_roster_changes(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4101, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-preview-owner',
            'current_name' => 'CSV Preview Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'CSV Preview', 'csv-preview');
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Stable Existing',
            'game_player_id' => 'stable-1',
            'state' => 'active',
        ])->assertRedirect();
        $this->withSession($session)->post('/alliance/roster', [
            'name' => 'Same Name',
            'state' => 'tracked',
        ])->assertRedirect();

        $csv = $this->csv([
            ['stable-1', 'Stable Renamed', '200', '30', 'TAG', 'R4', 'active', '2026-08-01', '2026-08-08T18:00:00Z'],
            ['stable-2', 'Brand New', '300', '31', 'TAG', 'R3', 'tracked', '2026-08-02', '2026-08-08T18:01:00Z'],
            ['', 'Same Name', '400', '', 'TAG', 'R2', 'active', '', '2026-08-08T18:02:00Z'],
        ]);

        $this->withSession($session)
            ->post('/alliance/roster/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('roster.csv', $csv),
            ])->assertRedirect();

        $import = RosterImport::query()->sole();
        self::assertSame(3, $import->row_count);
        self::assertSame(1, $import->create_count);
        self::assertSame(1, $import->update_count);
        self::assertSame(1, $import->ambiguous_count);
        self::assertSame(0, $import->rejected_count);
        self::assertSame(['update', 'create', 'ambiguous'], array_column($import->preview_payload['rows'], 'outcome'));
        self::assertSame(2, AllianceRosterEntry::query()->where('alliance_id', $alliance->id)->count());
        $this->assertDatabaseCount('player_snapshots', 0);
    }

    public function test_commit_requires_ambiguity_resolution_preserves_private_fields_and_player_identity_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4102, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-commit-owner',
            'current_name' => 'CSV Commit Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'ambiguous-4102',
            'current_name' => 'Ambiguous Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'CSV Commit', 'csv-commit');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Ambiguous Player',
            'game_player_id' => 'ambiguous-4102',
            'state' => 'active',
            'manager_notes' => 'Private note must survive CSV update.',
        ])->assertRedirect();
        $entry = AllianceRosterEntry::query()->sole();
        self::assertSame($memberPlayer->id, $entry->player_id);

        $csv = $this->csv([
            ['', 'Ambiguous Player', '123456789', '32', 'ALLY', 'R4', 'tracked', '2026-07-01', '2026-08-08T18:10:00Z'],
        ]);

        $this->withSession($session)->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('ambiguous.csv', $csv),
        ])->assertRedirect();
        $import = RosterImport::query()->sole();

        $this->from('/alliance/roster/import/'.$import->id)
            ->withSession($session)
            ->post('/alliance/roster/import/'.$import->id.'/commit', ['resolutions' => []])
            ->assertRedirect('/alliance/roster/import/'.$import->id)
            ->assertSessionHasErrors('resolutions.2');
        $this->assertDatabaseCount('player_snapshots', 0);

        $this->withSession($session)
            ->post('/alliance/roster/import/'.$import->id.'/commit', [
                'resolutions' => ['2' => (string) $entry->id],
            ])->assertRedirect();

        $entry->refresh();
        $import->refresh();
        self::assertSame('tracked', $entry->state->value);
        self::assertSame('csv', $entry->source);
        self::assertSame($memberPlayer->id, $entry->player_id);
        self::assertSame('Private note must survive CSV update.', $entry->manager_notes);
        self::assertSame(RosterImport::STATUS_COMMITTED, $import->status);
        self::assertSame(1, $import->committed_summary['rows_committed'] ?? null);
        self::assertSame(1, $import->committed_summary['roster_entries_updated'] ?? null);

        $this->assertDatabaseHas('player_snapshots', [
            'alliance_id' => $alliance->id,
            'roster_entry_id' => $entry->id,
            'player_id' => $memberPlayer->id,
            'roster_import_id' => $import->id,
            'source' => 'csv',
            'power' => 123456789,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_player_id' => $ownerPlayer->id,
            'event' => 'intelligence.roster_import_committed',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'intelligence.roster_import_committed',
        ]);

        $this->withSession($session)
            ->post('/alliance/roster/import/'.$import->id.'/commit', [
                'resolutions' => ['2' => (string) $entry->id],
            ])->assertRedirect();
        $this->assertDatabaseCount('player_snapshots', 1);
        self::assertSame(1, AuditEvent::query()->where('event', 'intelligence.roster_import_committed')->count());

        $this->withSession($session)->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('same-content.csv', $csv),
        ])->assertRedirect();
        $this->assertDatabaseCount('kingdom_roster_imports', 1);
        $this->assertDatabaseCount('player_snapshots', 1);
    }

    public function test_rejected_rows_and_preview_drift_fail_closed(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4103, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-fail-owner',
            'current_name' => 'CSV Fail Owner',
        ]);
        $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'CSV Fail Closed', 'csv-fail-closed');
        $session = $this->confirmedSession($ownerPlayer->id);
        $this->actingAs($owner);

        $rejectedCsv = $this->csv([
            ['reject-1', 'Rejected', 'not-a-number', '', '', '', 'active', '', '2026-08-08T18:20:00Z'],
        ]);
        $this->withSession($session)->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('rejected.csv', $rejectedCsv),
        ])->assertRedirect();
        $rejected = RosterImport::query()->sole();
        self::assertSame(1, $rejected->rejected_count);

        $this->from('/alliance/roster/import/'.$rejected->id)
            ->withSession($session)
            ->post('/alliance/roster/import/'.$rejected->id.'/commit')
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('alliance_roster_entries', 0);

        $freshCsv = $this->csv([
            ['drift-1', 'Preview Create', '500', '', '', 'R1', 'active', '', '2026-08-08T18:21:00Z'],
        ]);
        $this->withSession($session)->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('drift.csv', $freshCsv),
        ])->assertRedirect();
        $drift = RosterImport::query()->where('checksum', hash('sha256', $freshCsv))->sole();
        self::assertSame(1, $drift->create_count);

        $this->withSession($session)->post('/alliance/roster', [
            'name' => 'Created After Preview',
            'game_player_id' => 'drift-1',
            'state' => 'active',
        ])->assertRedirect();

        $this->from('/alliance/roster/import/'.$drift->id)
            ->withSession($session)
            ->post('/alliance/roster/import/'.$drift->id.'/commit')
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('player_snapshots', 0);
    }

    public function test_import_access_is_permission_and_active_player_tenant_scoped(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4104, 'status' => 'active']);
        $firstOwnerPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-first-owner',
            'current_name' => 'CSV First Owner',
        ]);
        $secondOwnerPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-second-owner',
            'current_name' => 'CSV Second Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-member',
            'current_name' => 'CSV Member',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwnerPlayer, 'CSV Tenant One', 'csv-tenant-one');
        $second = $createAlliance->handle($secondOwnerPlayer, 'CSV Tenant Two', 'csv-tenant-two');
        AllianceMembership::query()->create([
            'alliance_id' => $first->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $csv = $this->csv([
            ['tenant-1', 'Tenant Player', '100', '', '', '', 'active', '', '2026-08-08T18:30:00Z'],
        ]);
        $this->actingAs($firstOwner)
            ->withSession($this->confirmedSession($firstOwnerPlayer->id))
            ->post('/alliance/roster/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('tenant.csv', $csv),
            ])->assertRedirect();
        $import = RosterImport::query()->sole();

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($secondOwnerPlayer->id))
            ->get('/alliance/roster/import/'.$import->id)
            ->assertNotFound();
        $this->post('/alliance/roster/import/'.$import->id.'/commit')->assertNotFound();

        $this->actingAs($member)
            ->withSession($this->confirmedSession($memberPlayer->id))
            ->get('/alliance/roster/import')
            ->assertForbidden();
        $this->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('member.csv', $csv),
        ])->assertForbidden();
    }

    public function test_exports_are_private_formula_safe_and_management_fields_are_permission_gated(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4105, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-export-owner',
            'current_name' => 'CSV Export Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-export-member',
            'current_name' => 'CSV Export Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'CSV Export', 'csv-export');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $session = $this->confirmedSession($ownerPlayer->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => '=HYPERLINK("https://example.test")',
            'game_player_id' => 'export-1',
            'game_role' => '+cmd',
            'state' => 'active',
            'manager_notes' => '@SUM(1,1)',
        ])->assertRedirect();
        $entry = AllianceRosterEntry::query()->sole();
        $this->app->make(RecordPlayerSnapshot::class)->handle($alliance, $ownerPlayer, (string) $entry->id, [
            'observed_name' => '=HYPERLINK("https://example.test")',
            'power' => '9000',
            'progression_level' => '35',
            'observed_alliance_tag' => '-DANGER',
            'captured_at' => '2026-08-08T18:40:00Z',
        ]);

        $memberResponse = $this->actingAs($member)
            ->withSession([(string) config('game_world.active_player_session_key') => $memberPlayer->id])
            ->get('/alliance/roster/export.csv?scope=member')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = $memberResponse->headers->get('Cache-Control');
        self::assertIsString($cacheControl);
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('max-age=0', $cacheControl);
        $memberContent = $memberResponse->getContent();
        self::assertIsString($memberContent);
        self::assertStringNotContainsString('manager_notes', $memberContent);
        self::assertStringNotContainsString('@SUM(1,1)', $memberContent);
        self::assertStringContainsString("'=HYPERLINK", $memberContent);
        self::assertStringContainsString("'-DANGER", $memberContent);
        self::assertStringContainsString("'+cmd", $memberContent);

        $this->get('/alliance/roster/export.csv?scope=management')->assertForbidden();

        $managerResponse = $this->actingAs($owner)
            ->withSession([(string) config('game_world.active_player_session_key') => $ownerPlayer->id])
            ->get('/alliance/roster/export.csv?scope=management')
            ->assertOk();
        $managerContent = $managerResponse->getContent();
        self::assertIsString($managerContent);
        self::assertStringContainsString('manager_notes', $managerContent);
        self::assertStringContainsString("'@SUM(1,1)", $managerContent);
    }

    public function test_parser_enforces_maximum_row_count_and_file_size(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4106, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'csv-limits-owner',
            'current_name' => 'CSV Limits Owner',
        ]);
        $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'CSV Limits', 'csv-limits');
        $session = $this->confirmedSession($ownerPlayer->id);
        $this->actingAs($owner);

        $rows = [];
        for ($index = 1; $index <= RosterCsvParser::MAX_ROWS; $index++) {
            $rows[] = [
                'limit-'.$index,
                'Player '.$index,
                (string) (1000 + $index),
                '',
                '',
                '',
                'active',
                '',
                '2026-08-08T18:50:00Z',
            ];
        }

        $this->withSession($session)->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('max.csv', $this->csv($rows)),
        ])->assertRedirect();
        self::assertSame(RosterCsvParser::MAX_ROWS, RosterImport::query()->sole()->row_count);

        $rows[] = ['limit-over', 'Too Many', '1', '', '', '', 'active', '', '2026-08-08T18:50:00Z'];
        $this->from('/alliance/roster/import')
            ->withSession($session)
            ->post('/alliance/roster/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('too-many.csv', $this->csv($rows)),
            ])->assertSessionHasErrors('file');

        $this->from('/alliance/roster/import')
            ->withSession($session)
            ->post('/alliance/roster/import/preview', [
                'file' => UploadedFile::fake()->createWithContent(
                    'too-large.csv',
                    str_repeat('x', RosterCsvParser::MAX_BYTES + 1),
                ),
            ])->assertSessionHasErrors('file');
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'w+b');
        self::assertNotFalse($handle);
        fputcsv($handle, RosterCsvParser::HEADERS, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        self::assertIsString($content);

        return $content;
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $playerId): array
    {
        return [
            (string) config('game_world.active_player_session_key') => $playerId,
            'auth.password_confirmed_at' => time(),
        ];
    }
}
