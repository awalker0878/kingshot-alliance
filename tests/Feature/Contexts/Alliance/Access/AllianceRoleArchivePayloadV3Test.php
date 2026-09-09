<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\Alliance\Access;

use App\Contexts\Alliance\Access\Actions\ArchiveAllianceRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceRoleArchivePayloadV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{int}> */
    public static function assignmentCounts(): iterable
    {
        yield 'empty' => [0];
        yield 'one' => [1];
        yield 'historical population' => [1000];
    }

    #[DataProvider('assignmentCounts')]
    public function test_archival_revokes_in_one_scoped_statement_with_bounded_exact_metadata(int $count): void
    {
        $s = $this->scenario($count);
        $other = $this->scenario(1);
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query;
        });

        app(ArchiveAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['roleId']);

        $archiveQueries = $queries;
        self::assertLessThanOrEqual(25, count($archiveQueries), 'Work must not grow with assignment cardinality.');
        $deletes = array_values(array_filter($archiveQueries, static fn (QueryExecuted $query): bool => str_starts_with($query->sql, 'delete from "membership_roles"')));
        self::assertCount(1, $deletes);
        self::assertContains($s['alliance']->allianceId, $deletes[0]->bindings);
        self::assertContains($s['roleId'], $deletes[0]->bindings);
        foreach ($archiveQueries as $query) {
            self::assertFalse(
                str_starts_with($query->sql, 'select') && str_contains($query->sql, 'membership_roles') && in_array($s['roleId'], $query->bindings, true),
                'Archival must not read the role assignment population into memory.',
            );
        }

        $audit = AuditEvent::query()->where('event', 'alliance.role_archived')->where('subject_id', $s['roleId'])->sole();
        $outbox = OutboxMessage::query()->where('event_type', 'alliance.role_archived')->where('aggregate_id', $s['roleId'])->sole();
        foreach ([$audit->metadata, $outbox->payload] as $payload) {
            self::assertSame($count, $payload['removed_membership_count']);
            self::assertArrayNotHasKey('removed_membership_ids', $payload);
            self::assertLessThan(512, strlen(json_encode($payload, JSON_THROW_ON_ERROR)));
        }
        self::assertSame(0, DB::table('membership_roles')->where('role_id', $s['roleId'])->count());
        self::assertNotNull(Role::query()->findOrFail($s['roleId'])->archived_at);
        self::assertSame(1, DB::table('membership_roles')->where('role_id', $other['roleId'])->count());
        self::assertNull(Role::query()->findOrFail($other['roleId'])->archived_at);
        $beforeRetry = $this->state();
        app(ArchiveAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['roleId']);
        self::assertSame($beforeRetry, $this->state());
    }

    public function test_late_failure_restores_assignments_and_does_not_record_an_archive(): void
    {
        $s = $this->scenario(1000);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('alliance.role_archived', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected archival audit failure.');
            }
        });
        try {
            app(ArchiveAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['roleId']);
            self::fail('Archival must fail atomically.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected archival audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{alliance:AllianceReference,leader:PlayerReference,roleId:string} */
    private function scenario(int $count): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $leader = $factory->player((int) $user->id, 59255);
        $alliance = $factory->alliance($leader);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $leader->playerId, 'Historical specialist', []);
        foreach (array_chunk(range(1, max(1, $count)), 250) as $chunk) {
            if ($count === 0) {
                break;
            }
            $players = $memberships = $assignments = [];
            foreach ($chunk as $number) {
                $playerId = (string) Str::ulid();
                $membershipId = (string) Str::ulid();
                $players[] = ['id' => $playerId, 'current_kingdom_id' => $alliance->kingdomId, 'current_name' => 'Historical Governor '.$number];
                $memberships[] = [
                    'id' => $membershipId, 'alliance_id' => $alliance->allianceId, 'player_id' => $playerId,
                    'status' => MembershipStatus::Suspended->value, 'rank' => AllianceRank::R1->value,
                ];
                $assignments[] = ['alliance_id' => $alliance->allianceId, 'membership_id' => $membershipId, 'role_id' => $roleId];
            }
            DB::table('players')->insert($players);
            DB::table('alliance_memberships')->insert($memberships);
            DB::table('membership_roles')->insert($assignments);
        }

        return compact('alliance', 'leader', 'roleId');
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return [
            'roles' => DB::table('roles')->orderBy('id')->get()->toJson(),
            'assignments' => DB::table('membership_roles')->orderBy('membership_id')->orderBy('role_id')->get()->toJson(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
