<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Models\RosterImport;
use App\Domain\Kingdoms\Services\RosterCsvParser;
use App\Domain\Memberships\Enums\AllianceRank;
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
        $kingdom = Kingdom::query()->create(['number' => 5001, 'status' => 'active']);
        $ownerPlayer = $this->player($owner, $kingdom, 'accepted-kingdoms-r5', 'Accepted Kingdoms R5');
        $memberPlayer = $this->player($member, $kingdom, 'accept-1', 'Manual Player');
        $otherOwnerPlayer = $this->player($otherOwner, $kingdom, 'other-accepted-kingdoms-r5', 'Other Accepted Kingdoms R5');
        $createAlliance = $this->app->make(CreateAlliance::class);
        $alliance = $createAlliance->handle($ownerPlayer, 'Accepted Kingdoms', 'accepted-kingdoms');
        $otherAlliance = $createAlliance->handle($otherOwnerPlayer, 'Other Accepted Kingdoms', 'other-accepted-kingdoms');
        $membership = $this->addMember($alliance->id, $memberPlayer);
        $confirmed = $this->confirmedSession($ownerPlayer->id);

        self::assertNotNull($alliance->kingdom_id);
        self::assertFalse(Schema::hasColumn('alliances', 'kingdom'));
        self::assertSame($alliance->kingdom_id, $otherAlliance->kingdom_id);

        $this->actingAs($owner)
            ->withSession($confirmed)
            ->post('/alliance/roster', [
                'name' => 'Manual Player',
                'game_player_id' => 'accept-1',
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
        self::assertSame($memberPlayer->id, $entry->player_id);
        self::assertSame($membership->id, $entry->player->memberships()->where('alliance_id', $alliance->id)->sole()->id);
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

        $this->withSession($this->activeSession($ownerPlayer->id))
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
            ->withSession($this->activeSession($memberPlayer->id))
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
            ->withSession($this->confirmedSession($otherOwnerPlayer->id))
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

    private function addMember(string $allianceId, Player $player): AllianceMembership
    {
        return AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => $player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
    }

    private function player(User $user, Kingdom $kingdom, string $gamePlayerId, string $name): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
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
    private function activeSession(string $playerId): array
    {
        return [
            (string) config('identity.active_player_session_key') => $playerId,
        ];
    }

    /** @return array<string, int|string> */
    private function confirmedSession(string $playerId): array
    {
        return [
            ...$this->activeSession($playerId),
            'auth.password_confirmed_at' => time(),
        ];
    }
}
