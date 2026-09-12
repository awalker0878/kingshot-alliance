<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Access\Integration\Concurrency;

use App\Contexts\Alliance\Access\Actions\ArchiveAllianceRole;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\UpdateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Content\Actions\SaveContentCategory;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceRoleRevocationConcurrencyV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function revocations(): iterable
    {
        yield 'permissions removed' => [false];
        yield 'role archived' => [true];
    }

    #[DataProvider('revocations')]
    public function test_role_revocation_waits_for_an_admitted_protected_writer(bool $archive): void
    {
        $s = $this->scenario();
        $primary = $this->configureCompetitor();
        $attempted = false;
        $blocked = false;
        DB::listen(function (QueryExecuted $query) use ($s, $primary, $archive, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'insert into "content_categories"')) {
                return;
            }
            $attempted = true;
            $blocked = $this->isBlocked(fn () => $this->revoke($s, $archive));
        });

        try {
            $id = app(SaveContentCategory::class)->handle($s['alliance']->allianceId, $s['writer']->playerId, 'Admitted category', 'admitted');
            self::assertTrue($attempted && $blocked, 'Role revocation must wait for the admitted writer to commit.');
            self::assertTrue(DB::table('content_categories')->where('id', $id)->exists());
            $this->onCompetitor(fn () => $this->revoke($s, $archive));
            $this->assertWriterRejected($s);
            self::assertSame(1, DB::table('content_categories')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('role_competitor');
        }
    }

    #[DataProvider('revocations')]
    public function test_writer_waits_for_role_revocation_and_rejects_obsolete_authority_on_retry(bool $archive): void
    {
        $s = $this->scenario();
        $primary = $this->configureCompetitor();
        $attempted = false;
        $blocked = false;
        DB::listen(function (QueryExecuted $query) use ($s, $primary, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'update "roles"')) {
                return;
            }
            $attempted = true;
            $blocked = $this->isBlocked(fn () => app(SaveContentCategory::class)->handle($s['alliance']->allianceId, $s['writer']->playerId, 'Stale category', 'stale'));
        });

        try {
            $this->revoke($s, $archive);
            self::assertTrue($attempted && $blocked, 'A writer must not read the pre-commit version of revoked role authority.');
            $this->onCompetitor(fn () => $this->assertWriterRejected($s));
            self::assertSame(0, DB::table('content_categories')->count());
            self::assertSame(0, DB::table('audit_events')->where('event', 'content.category_created')->count());
            self::assertSame(0, DB::table('outbox_messages')->where('event_type', 'content.category_created')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('role_competitor');
        }
    }

    #[DataProvider('revocations')]
    public function test_role_revocation_does_not_block_a_different_alliance(bool $archive): void
    {
        $s = $this->scenario();
        $other = $this->scenario();
        $primary = $this->configureCompetitor();
        $wrote = false;
        DB::listen(function (QueryExecuted $query) use ($other, $primary, &$wrote): void {
            if ($wrote || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'update "roles"')) {
                return;
            }
            $wrote = true;
            $this->onCompetitor(fn () => app(SaveContentCategory::class)->handle($other['alliance']->allianceId, $other['writer']->playerId, 'Other Alliance', 'independent'));
        });

        try {
            $this->revoke($s, $archive);
            self::assertTrue($wrote);
            self::assertSame(1, DB::table('content_categories')->where('alliance_id', $other['alliance']->allianceId)->count());
            self::assertSame(0, DB::table('content_categories')->where('alliance_id', $s['alliance']->allianceId)->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('role_competitor');
        }
    }

    #[DataProvider('revocations')]
    public function test_late_revocation_audit_failure_preserves_role_authority_and_assignments(bool $archive): void
    {
        $s = $this->scenario();
        $before = $this->state($s['roleId']);
        $event = $archive ? 'alliance.role_archived' : 'alliance.role_updated';
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($event, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array($event, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected role revocation audit failure.');
            }
        });
        try {
            $this->revoke($s, $archive);
            self::fail('The real late audit failure must abort revocation.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected role revocation audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state($s['roleId']));
        app(SaveContentCategory::class)->handle($s['alliance']->allianceId, $s['writer']->playerId, 'Still authorized', 'still-authorized');
        self::assertSame(1, DB::table('content_categories')->count());
    }

    /** @return array{alliance:AllianceReference,leader:PlayerReference,writer:PlayerReference,roleId:string} */
    private function scenario(): array
    {
        $factory = new ScenarioFactory;
        $leader = $factory->player((int) $factory->authUser()->id, 59240);
        $alliance = $factory->alliance($leader);
        $writer = $factory->player((int) $factory->authUser()->id, 59240);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $writer->playerId,
            'status' => MembershipStatus::Active, 'rank' => AllianceRank::R1, 'joined_at' => now(),
        ]);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $leader->playerId, 'Delegated content', [AlliancePermission::ContentManage]);
        app(AssignMembershipRole::class)->handle($alliance->allianceId, $leader->playerId, (string) $membership->id, $roleId);

        return compact('alliance', 'leader', 'writer', 'roleId');
    }

    /** @param array{alliance:AllianceReference,leader:PlayerReference,writer:PlayerReference,roleId:string} $s */
    private function revoke(array $s, bool $archive): void
    {
        if ($archive) {
            app(ArchiveAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['roleId']);
        } else {
            app(UpdateAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, $s['roleId'], 'Revoked content', []);
        }
    }

    /** @param array{alliance:AllianceReference,leader:PlayerReference,writer:PlayerReference,roleId:string} $s */
    private function assertWriterRejected(array $s): void
    {
        try {
            app(SaveContentCategory::class)->handle($s['alliance']->allianceId, $s['writer']->playerId, 'Rejected category', 'rejected');
            self::fail('Current revoked authority must reject the protected write.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
    }

    private function configureCompetitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.role_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'role_competitor']));
        DB::connection('role_competitor')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    private function onCompetitor(Closure $operation): void
    {
        $primary = DB::getDefaultConnection();
        DB::setDefaultConnection('role_competitor');
        try {
            $operation();
        } finally {
            DB::setDefaultConnection($primary);
        }
    }

    private function isBlocked(Closure $operation): bool
    {
        try {
            $this->onCompetitor($operation);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '55P03') {
                throw $exception;
            }

            return true;
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function state(string $roleId): array
    {
        return [
            'role' => Role::query()->findOrFail($roleId)->getRawOriginal(),
            'permissions' => DB::table('role_permissions')->where('role_id', $roleId)->orderBy('permission_id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'assignments' => DB::table('membership_roles')->where('role_id', $roleId)->orderBy('membership_id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
