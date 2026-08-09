<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\RosterImport;
use App\Domain\Kingdoms\Services\RosterCsvParser;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomIncrementAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_complete_increment_works_from_kingdom_and_manual_roster_through_history_csv_intelligence_and_tenant_isolation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-08 20:00:00 UTC'));
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $alliance = $createAlliance->handle($owner, 'Accepted Kingdoms', 'accepted-kingdoms', 5001);
        $otherAlliance = $createAlliance->handle($otherOwner, 'Other Accepted Kingdoms', 'other-accepted-kingdoms', 5001);
        $membership = $this->addMember($alliance->id, $member);
        $confirmed = $this->confirmedSession($alliance->id);

        self::assertNotNull($alliance->kingdom_id);
        self::assertFalse(Schema::hasColumn('alliances', 'kingdom'));
        self::assertSame($alliance->kingdom_id, $otherAlliance->kingdom_id);

        $this->actingAs($owner)
            ->withSession($confirmed)
            ->post('/alliance/roster', [
                'name' => 'Manual Player',
                'game_player_id' => 'accept-1',
                'membership_id' => $membership->id,
                'game_role' => 'R4',
                'state' => 'active',
                'joined_at' => now()->subDays(20)->toDateString(),
                'manager_notes' => 'Private acceptance note.',
            ])->assertRedirect();

        $entry = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('observed_name', 'Manual Player')
            ->sole();

        $this->withSession($confirmed)
            ->post('/alliance/roster/'.$entry->id.'/snapshots', [
                'observed_name' => 'Manual Player',
                'power' => '100',
                'progression_level' => '30',
                'observed_alliance_tag' => 'TAG',
                'captured_at' => now()->subDays(8)->toIso8601String(),
            ])->assertRedirect();

        $csv = $this->csv([
            [
                'accept-1',
                'Manual Player Renamed',
                '150',
                '31',
                'TAG',
                'R4',
                'active',
                now()->subDays(20)->toDateString(),
                now()->subHour()->toIso8601String(),
            ],
            [
                'accept-2',
                'Imported Player',
                '200',
                '29',
                'TAG',
                'R3',
                'tracked',
                now()->subDays(2)->toDateString(),
                now()->subMinutes(30)->toIso8601String(),
            ],
        ]);

        $this->withSession($confirmed)
            ->post('/alliance/roster/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('acceptance.csv', $csv),
            ])->assertRedirect();

        $import = RosterImport::query()->sole();
        self::assertSame(1, $import->create_count);
        self::assertSame(1, $import->update_count);
        self::assertSame(0, $import->ambiguous_count);
        self::assertSame(0, $import->rejected_count);

        $this->withSession($confirmed)
            ->post('/alliance/roster/import/'.$import->id.'/commit', ['resolutions' => []])
            ->assertRedirect();

        $entry->refresh();
        self::assertSame('Manual Player Renamed', $entry->observed_name);
        self::assertSame($membership->id, $entry->membership_id);
        self::assertSame('Private acceptance note.', $entry->manager_notes);
        self::assertSame('csv', $entry->source);
        $this->assertDatabaseCount('player_snapshots', 3);
        $this->assertDatabaseHas('player_snapshots', [
            'alliance_id' => $alliance->id,
            'roster_entry_id' => $entry->id,
            'roster_import_id' => $import->id,
            'source' => 'csv',
            'power' => 150,
        ]);

        $this->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster/intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/RosterIntelligence')
                ->where('canManage', true)
                ->where('metrics.trackedPlayers', 2)
                ->where('metrics.recordedPowerPlayers', 2)
                ->where('metrics.totalPower', '350')
                ->where('metrics.sevenDayTrend.change', '50')
                ->where('metrics.sevenDayTrend.comparablePlayers', 1));

        $this->actingAs($member)
            ->withSession($this->activeSession($alliance->id))
            ->get('/alliance/roster')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Alliance/Roster')
                ->where('canManage', false)
                ->has('entries', 2)
                ->missing('entries.0.managerNotes'));

        $this->get('/alliance/roster/manage')->assertForbidden();
        $this->get('/alliance/roster/export.csv?scope=management')->assertForbidden();
        $this->get('/alliance/roster/export.csv?scope=member')->assertOk();

        $this->actingAs($otherOwner)
            ->withSession($this->confirmedSession($otherAlliance->id))
            ->get('/alliance/roster/import/'.$import->id)
            ->assertNotFound();
        $this->get('/alliance/roster/'.$entry->id.'/history')->assertNotFound();

        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'kingdoms.roster_import_committed',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'kingdoms.roster_import_committed',
        ]);
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

    /** @return array<string, string> */
    private function activeSession(string $allianceId): array
    {
        return [
            (string) config('identity.active_alliance_session_key') => $allianceId,
        ];
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $allianceId): array
    {
        return [
            ...$this->activeSession($allianceId),
            'auth.password_confirmed_at' => time(),
        ];
    }
}
