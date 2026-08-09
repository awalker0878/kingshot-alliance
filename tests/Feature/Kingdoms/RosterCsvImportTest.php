<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\RecordPlayerSnapshot;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\RosterImport;
use App\Domain\Kingdoms\Services\RosterCsvParser;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class RosterCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_classifies_stable_updates_creates_and_name_ambiguity_without_persisting_roster_changes(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'CSV Preview', 'csv-preview', 4101);
        $session = $this->confirmedSession($alliance->id);

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

    public function test_commit_requires_ambiguity_resolution_preserves_private_fields_and_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'CSV Commit', 'csv-commit', 4102);
        $membership = $this->addMember($alliance->id, $member);
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => 'Ambiguous Player',
            'membership_id' => $membership->id,
            'state' => 'active',
            'manager_notes' => 'Private note must survive CSV update.',
        ])->assertRedirect();
        $entry = AllianceRosterEntry::query()->sole();

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
        self::assertSame($membership->id, $entry->membership_id);
        self::assertSame('Private note must survive CSV update.', $entry->manager_notes);
        self::assertSame(RosterImport::STATUS_COMMITTED, $import->status);
        self::assertSame(1, $import->committed_summary['rows_committed'] ?? null);
        self::assertSame(1, $import->committed_summary['roster_entries_updated'] ?? null);

        $this->assertDatabaseHas('player_snapshots', [
            'alliance_id' => $alliance->id,
            'roster_entry_id' => $entry->id,
            'roster_import_id' => $import->id,
            'source' => 'csv',
            'power' => 123456789,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'kingdoms.roster_import_committed',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.roster_import_committed',
        ]);

        $this->withSession($session)
            ->post('/alliance/roster/import/'.$import->id.'/commit', [
                'resolutions' => ['2' => (string) $entry->id],
            ])->assertRedirect();
        $this->assertDatabaseCount('player_snapshots', 1);
        self::assertSame(1, AuditEvent::query()
            ->where('event', 'kingdoms.roster_import_committed')->count());

        $this->withSession($session)->post('/alliance/roster/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('same-content.csv', $csv),
        ])->assertRedirect();
        $this->assertDatabaseCount('kingdom_roster_imports', 1);
        $this->assertDatabaseCount('player_snapshots', 1);
    }

    public function test_rejected_rows_and_preview_drift_fail_closed(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'CSV Fail Closed', 'csv-fail-closed', 4103);
        $session = $this->confirmedSession($alliance->id);
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

    public function test_import_access_is_permission_and_tenant_scoped(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $member = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'CSV Tenant One', 'csv-tenant-one', 4104);
        $second = $createAlliance->handle($secondOwner, 'CSV Tenant Two', 'csv-tenant-two', 4104);
        $this->addMember($first->id, $member);

        $csv = $this->csv([
            ['tenant-1', 'Tenant Player', '100', '', '', '', 'active', '', '2026-08-08T18:30:00Z'],
        ]);
        $this->actingAs($firstOwner)
            ->withSession($this->confirmedSession($first->id))
            ->post('/alliance/roster/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('tenant.csv', $csv),
            ])->assertRedirect();
        $import = RosterImport::query()->sole();

        $this->actingAs($secondOwner)
            ->withSession($this->confirmedSession($second->id))
            ->get('/alliance/roster/import/'.$import->id)
            ->assertNotFound();
        $this->post('/alliance/roster/import/'.$import->id.'/commit')->assertNotFound();

        $this->actingAs($member)
            ->withSession($this->confirmedSession($first->id))
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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'CSV Export', 'csv-export', 4105);
        $this->addMember($alliance->id, $member);
        $session = $this->confirmedSession($alliance->id);

        $this->actingAs($owner)->withSession($session)->post('/alliance/roster', [
            'name' => '=HYPERLINK("https://example.test")',
            'game_player_id' => 'export-1',
            'game_role' => '+cmd',
            'state' => 'active',
            'manager_notes' => '@SUM(1,1)',
        ])->assertRedirect();
        $entry = AllianceRosterEntry::query()->sole();
        $this->app->make(RecordPlayerSnapshot::class)->handle($alliance, $owner, (string) $entry->id, [
            'observed_name' => '=HYPERLINK("https://example.test")',
            'power' => '9000',
            'progression_level' => '35',
            'observed_alliance_tag' => '-DANGER',
            'captured_at' => '2026-08-08T18:40:00Z',
        ]);

        $memberResponse = $this->actingAs($member)
            ->withSession([(string) config('identity.active_alliance_session_key') => $alliance->id])
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
            ->withSession([(string) config('identity.active_alliance_session_key') => $alliance->id])
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
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'CSV Limits', 'csv-limits', 4106);
        $session = $this->confirmedSession($alliance->id);
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

    private function addMember(string $allianceId, User $user): AllianceMembership
    {
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $allianceId)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $allianceId]);

        return $membership;
    }

    /** @param  list<list<string>>  $rows */
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
    private function confirmedSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
            'auth.password_confirmed_at' => time(),
        ];
    }
}
