<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\KingPerks\Integration;

use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\KingPerks\Actions\QueueDueKingPerkReminders;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkReminderCursor;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Contexts\Operations\KingPerks\Support\KingPerkReminderSourceFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingPerkReminderTraversalTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-11 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_recipient_queries_are_bounded_before_materialization(): void
    {
        $this->source(7);
        $queries = [];
        DB::listen(static function (QueryExecuted $event) use (&$queries): void {
            if (str_contains($event->sql, 'select') && str_contains($event->sql, '"player_id"')
                && str_contains($event->sql, '"kingdom_role_assignments"') && ! str_contains($event->sql, 'for update')) {
                $queries[] = $event->sql;
            }
        });
        app(QueueDueKingPerkReminders::class)->handle(2);
        self::assertNotEmpty($queries);
        foreach ($queries as $sql) {
            self::assertMatchesRegularExpression('/\blimit [0-9]+\b/i', $sql, 'The manager projection must be bounded in SQL.');
        }
        self::assertSame(2, $this->messages()->count());
    }

    public function test_small_sweeps_resume_and_replays_consume_the_work_budget(): void
    {
        $this->source(7);
        $result = app(QueueDueKingPerkReminders::class)->handle(2);
        self::assertSame(2, $result->workUnits);
        self::assertSame(2, $result->queued);
        for ($i = 0; $i < 3; $i++) {
            $result = app(QueueDueKingPerkReminders::class)->handle(2);
            self::assertLessThanOrEqual(2, $result->workUnits);
        }
        self::assertSame(7, $this->messages()->count());
        $ids = $this->messages()->orderBy('id')->pluck('id')->all();
        $replay = app(QueueDueKingPerkReminders::class)->handle(2);
        self::assertLessThanOrEqual(2, $replay->workUnits);
        self::assertSame(0, $replay->queued);
        self::assertSame($ids, $this->messages()->orderBy('id')->pluck('id')->all());
    }

    public function test_a_large_earlier_source_does_not_starve_a_second_source(): void
    {
        [$owner, , $first] = $this->source(7);
        $second = $this->skill($owner, (string) $first->plan_id, KingSkill::FreshIdeas);
        app(QueueDueKingPerkReminders::class)->handle(1);
        app(QueueDueKingPerkReminders::class)->handle(1);
        self::assertSame(1, $this->messages()->where('subject_id', $first->id)->count());
        self::assertSame(1, $this->messages()->where('subject_id', $second->id)->count());
    }

    public function test_revocation_between_pages_prevents_new_intent_but_other_recipients_continue(): void
    {
        [, $players] = $this->source(6);
        app(QueueDueKingPerkReminders::class)->handle(2);
        $delivered = $this->messages()->pluck('player_id')->all();
        $revoked = collect($players)->first(fn (PlayerReference $p): bool => ! in_array($p->playerId, $delivered, true));
        self::assertInstanceOf(PlayerReference::class, $revoked);
        KingdomRoleAssignment::query()->where('player_id', $revoked->playerId)->update(['revoked_at' => now()]);
        for ($i = 0; $i < 4; $i++) {
            app(QueueDueKingPerkReminders::class)->handle(2);
        }
        self::assertSame(5, $this->messages()->count());
        self::assertFalse($this->messages()->where('player_id', $revoked->playerId)->exists());
    }

    public function test_a_source_larger_than_two_pages_resumes_without_a_shared_service_instance(): void
    {
        $this->source(53);
        $first = app(QueueDueKingPerkReminders::class)->handle(1000);
        self::assertSame(25, $first->recipientsExamined);
        self::assertSame(25, $first->queued);
        self::assertNotNull(KingPerkReminderCursor::query()->firstOrFail()->after_player_id);
        $connection = DB::getDefaultConnection();
        $workerConfiguration = config('database.connections.'.$connection);
        $workerDatabase = DB::connection()->getDatabaseName();
        $previousApplication = $this->app;
        $this->refreshApplication();
        // Laravel switches to the parallel database in its test-case setup callback,
        // not when an application is rebooted in the middle of a test. Preserve the
        // real worker target while replacing every application service instance.
        config()->set('database.default', $connection);
        config()->set('database.connections.'.$connection, $workerConfiguration);
        DB::purge($connection);
        self::assertNotSame($previousApplication, $this->app);
        self::assertSame($workerDatabase, DB::connection()->getDatabaseName());
        $second = app(QueueDueKingPerkReminders::class)->handle(1000);
        self::assertSame(25, $second->queued);
        $third = app(QueueDueKingPerkReminders::class)->handle(1000);
        self::assertSame(3, $third->queued);
        self::assertSame(53, $this->messages()->count());
        self::assertNull(KingPerkReminderCursor::query()->firstOrFail()->after_player_id);
    }

    public function test_revocation_after_projection_is_rechecked_and_the_denial_advances_progress(): void
    {
        [, $players] = $this->source(3);
        $ids = array_map(static fn (PlayerReference $p): string => $p->playerId, $players);
        sort($ids);
        $revoked = $ids[0];
        $called = false;
        DB::listen(static function (QueryExecuted $event) use (&$called, $revoked): void {
            if (! $called && str_starts_with($event->sql, 'select distinct "player_id"')) {
                $called = true;
                KingdomRoleAssignment::query()->where('player_id', $revoked)->update(['revoked_at' => now()]);
            }
        });
        $result = app(QueueDueKingPerkReminders::class)->handle(1);
        self::assertTrue($called);
        self::assertSame(1, $result->workUnits);
        self::assertSame(0, $result->queued);
        self::assertSame($revoked, KingPerkReminderCursor::query()->firstOrFail()->after_player_id);
        app(QueueDueKingPerkReminders::class)->handle(10);
        self::assertSame(2, $this->messages()->count());
        self::assertFalse($this->messages()->where('player_id', $revoked)->exists());
    }

    public function test_a_superseded_worker_cannot_rewind_another_connections_committed_cursor(): void
    {
        $this->source(5);
        $connection = DB::getDefaultConnection();
        config()->set('database.connections.reminder_competing', config('database.connections.'.$connection));
        $called = false;
        DB::listen(static function (QueryExecuted $event) use (&$called, $connection): void {
            if (! $called && str_starts_with($event->sql, 'select distinct "player_id"')) {
                $called = true;
                DB::setDefaultConnection('reminder_competing');
                try {
                    $winner = app(QueueDueKingPerkReminders::class)->handle(2);
                    self::assertSame(2, $winner->queued);
                } finally {
                    DB::setDefaultConnection($connection);
                    DB::purge('reminder_competing');
                }
            }
        });
        $loser = app(QueueDueKingPerkReminders::class)->handle(2);
        self::assertTrue($called);
        self::assertSame(1, $loser->supersededPages);
        self::assertSame(0, $loser->queued);
        self::assertSame(2, KingPerkReminderCursor::query()->firstOrFail()->version);
        self::assertSame(2, $this->messages()->count());
        self::assertSame(3, app(QueueDueKingPerkReminders::class)->handle(10)->queued);
        self::assertSame(5, $this->messages()->count());
    }

    public function test_outbox_failure_rolls_back_intent_delivery_and_cursor_advancement(): void
    {
        $this->source(2);
        $failed = false;
        DB::listen(static function (QueryExecuted $event) use (&$failed): void {
            if (! $failed && str_starts_with($event->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected reminder outbox failure.');
            }
        });
        try {
            app(QueueDueKingPerkReminders::class)->handle(1);
            self::fail('A failed outbox write must escape the action.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected reminder outbox failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame(0, $this->messages()->count());
        self::assertSame(0, KingPerkReminderCursor::query()->firstOrFail()->version);
        self::assertNull(KingPerkReminderCursor::query()->firstOrFail()->after_player_id);
        self::assertSame(1, app(QueueDueKingPerkReminders::class)->handle(1)->queued);
        self::assertSame(1, $this->messages()->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'king_perks.reminder.requested')->count());
    }

    public function test_rescheduled_source_is_rechecked_after_the_audience_was_selected(): void
    {
        [, , $skill] = $this->source(2);
        $changed = false;
        DB::listen(static function (QueryExecuted $event) use (&$changed, $skill): void {
            if (! $changed && str_starts_with($event->sql, 'select distinct "player_id"')) {
                $changed = true;
                KingSkillPlan::query()->whereKey($skill->id)->update(['planned_activation_at' => now()->addDays(5), 'planned_ends_at' => now()->addDays(5)->addHour()]);
            }
        });
        $result = app(QueueDueKingPerkReminders::class)->handle(1);
        self::assertTrue($changed);
        self::assertSame(1, $result->workUnits);
        self::assertSame(0, $result->queued);
        self::assertSame(0, $this->messages()->count());
    }

    public function test_source_deadline_is_rechecked_at_each_recipient_not_only_sweep_start(): void
    {
        $this->source(2);
        $advanced = false;
        DB::listen(static function (QueryExecuted $event) use (&$advanced): void {
            if (! $advanced && str_starts_with($event->sql, 'select distinct "player_id"')) {
                $advanced = true;
                CarbonImmutable::setTestNow('2026-09-11 15:00:00 UTC');
            }
        });
        $result = app(QueueDueKingPerkReminders::class)->handle(1);
        self::assertTrue($advanced);
        self::assertSame(1, $result->workUnits);
        self::assertSame(0, $result->queued);
        self::assertSame(0, $this->messages()->count());
    }

    public function test_recreated_cursor_cannot_be_advanced_by_a_stale_discovered_page(): void
    {
        $this->source(3);
        $replaced = false;
        $replacementId = null;
        DB::listen(static function (QueryExecuted $event) use (&$replaced, &$replacementId): void {
            if (! $replaced && str_starts_with($event->sql, 'select distinct "player_id"')) {
                $replaced = true;
                $old = KingPerkReminderCursor::query()->firstOrFail();
                $values = $old->only(['kind', 'source_id', 'version', 'visited_at', 'expires_at']);
                $old->delete();
                $replacementId = KingPerkReminderCursor::query()->create($values)->id;
            }
        });
        $result = app(QueueDueKingPerkReminders::class)->handle(2);
        self::assertTrue($replaced);
        self::assertSame(1, $result->supersededPages);
        self::assertSame(0, $result->queued);
        $cursor = KingPerkReminderCursor::query()->firstOrFail();
        self::assertSame($replacementId, $cursor->id);
        self::assertSame(0, $cursor->version);
        self::assertNull($cursor->after_player_id);
        self::assertSame(3, app(QueueDueKingPerkReminders::class)->handle(10)->queued);
    }

    public function test_new_authority_behind_the_boundary_is_visited_on_the_next_cycle(): void
    {
        [, $players] = $this->source(4);
        $grant = KingdomRoleAssignment::query()->where('player_id', $players[0]->playerId)->firstOrFail();
        $grant->update(['revoked_at' => now()]);
        self::assertSame(2, app(QueueDueKingPerkReminders::class)->handle(2)->queued);
        $grant->update(['revoked_at' => null]);
        self::assertSame(1, app(QueueDueKingPerkReminders::class)->handle(2)->queued);
        self::assertSame(1, app(QueueDueKingPerkReminders::class)->handle(2)->queued);
        self::assertSame(4, $this->messages()->count());
        self::assertSame(1, $this->messages()->where('player_id', $players[0]->playerId)->count());
    }

    public function test_empty_audiences_consume_budget_and_do_not_starve_other_sources(): void
    {
        [$owner, , $skill] = $this->source(1);
        $this->skill($owner, (string) $skill->plan_id, KingSkill::FreshIdeas);
        KingdomRoleAssignment::query()->where('player_id', $owner->playerId)->update(['revoked_at' => now()]);
        for ($i = 0; $i < 2; $i++) {
            $result = app(QueueDueKingPerkReminders::class)->handle(1);
            self::assertSame(1, $result->workUnits);
            self::assertSame(0, $result->recipientsExamined);
            self::assertSame(0, $result->queued);
        }
        self::assertSame(2, KingPerkReminderCursor::query()->count());
        self::assertSame(0, $this->messages()->count());
    }

    public function test_expired_progress_is_pruned_in_a_bounded_batch_without_owning_source_state(): void
    {
        for ($i = 0; $i < 103; $i++) {
            KingPerkReminderCursor::query()->create([
                'kind' => 'skill_1_hour', 'source_id' => (string) Str::ulid(),
                'visited_at' => now()->subDays(8), 'expires_at' => now()->subDay(),
            ]);
        }
        $result = app(QueueDueKingPerkReminders::class)->handle(1);
        self::assertSame(100, $result->expiredCursorsRemoved);
        self::assertSame(3, KingPerkReminderCursor::query()->count());
        self::assertSame(0, $result->workUnits);
    }

    public function test_the_command_reports_work_and_delivery_counts_separately(): void
    {
        $this->source(3);
        $this->artisan('king-perks:queue-reminders', ['--limit' => 2])
            ->expectsOutput('King Perks: work=2 sources=1 recipients=2 queued=2 superseded_pages=0 expired_cursors_removed=0')
            ->assertExitCode(0);
    }

    /** @return array{PlayerReference, list<PlayerReference>, KingSkillPlan} */
    private function source(int $audience): array
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId, 87103);
        $source = app(KingPerkReminderSourceFixture::class)->forPlayer($owner);
        KingPerkAppointment::query()->whereKey($source['appointment'])->update(['status' => 'cancelled']);
        $role = KingdomRoleAssignment::query()->where('player_id', $owner->playerId)->firstOrFail();
        $players = [$owner];
        for ($i = 1; $i < $audience; $i++) {
            $player = $factory->player($factory->account()->userId, 87103);
            KingdomRoleAssignment::query()->create([
                'kingdom_id' => $owner->kingdomId, 'player_id' => $player->playerId,
                'kingdom_role_id' => $role->kingdom_role_id, 'effective_from' => now()->subDay(),
            ]);
            $players[] = $player;
        }

        return [$owner, $players, $this->skill($owner, $source['plan'], KingSkill::Groundworks)];
    }

    private function skill(PlayerReference $owner, string $plan, KingSkill $kind): KingSkillPlan
    {
        return KingSkillPlan::query()->create([
            'plan_id' => $plan, 'skill_key' => $kind, 'planned_activation_at' => now()->addHours(2),
            'planned_ends_at' => now()->addHours(3), 'effect_duration_minutes' => 60,
            'planned_by_player_id' => $owner->playerId, 'status' => 'planned',
        ]);
    }

    private function messages(): Builder
    {
        return NotificationMessage::query()->where('notification_type', 'king_perks.reminder');
    }
}
