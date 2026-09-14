<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryActivity;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryActivityRecorder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Workflows\NotificationDelivery\Actions\QueueTerritoryNotifications;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryCollaborationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_competing_worker_skips_locked_activity_and_retry_queues_once(): void
    {
        $activityId = $this->activity();
        config()->set('database.connections.territory_collaboration_competitor', [...DB::connection()->getConfig(), 'name' => 'territory_collaboration_competitor']);
        $competitor = DB::connection('territory_collaboration_competitor');
        $competitor->beginTransaction();
        $competitor->table('territory_activities')->where('id', $activityId)->lockForUpdate()->first();
        try {
            self::assertSame(['pages' => 0, 'recipients' => 0, 'queued' => 0], app(QueueTerritoryNotifications::class)->handle(1, 1));
            self::assertSame(0, NotificationMessage::query()->where('notification_type', 'territory.activity')->count());
            $competitor->commit();
            self::assertSame(1, app(QueueTerritoryNotifications::class)->handle(1, 1)['queued']);
            self::assertSame(0, app(QueueTerritoryNotifications::class)->handle(1, 1)['queued']);
            self::assertNotNull(TerritoryActivity::query()->findOrFail($activityId)->completed_at);
        } finally {
            while ($competitor->transactionLevel() > 0) {
                $competitor->rollBack();
            }
            DB::purge('territory_collaboration_competitor');
        }
    }

    public function test_cursor_failure_rolls_back_message_and_retry_replays_the_same_source_page(): void
    {
        $activityId = $this->activity();
        $fail = true;
        DB::listen(static function (QueryExecuted $query) use (&$fail): void {
            if ($fail && str_starts_with($query->sql, 'update "territory_activities"')) {
                $fail = false;
                throw new RuntimeException('Cursor failure fixture.');
            }
        });
        try {
            app(QueueTerritoryNotifications::class)->handle(1, 1);
            self::fail('Cursor failure must roll back the delivery intent.');
        } catch (RuntimeException $exception) {
            self::assertSame('Cursor failure fixture.', $exception->getMessage());
        }
        self::assertSame(0, NotificationMessage::query()->where('notification_type', 'territory.activity')->count());
        $activity = TerritoryActivity::query()->findOrFail($activityId);
        self::assertNull($activity->completed_at);
        self::assertNull($activity->after_player_id);
        self::assertSame(1, app(QueueTerritoryNotifications::class)->handle(1, 1)['queued']);
        self::assertSame(1, NotificationMessage::query()->where('notification_type', 'territory.activity')->count());
    }

    private function activity(): string
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player((int) $factory->authUser()->id, 61378);
        $alliance = $factory->alliance($owner);
        $plan = app(CreateTerritoryPlan::class)->handle($owner->playerId, TerritoryPlanScope::Alliance, $owner->kingdomId,
            $alliance->allianceId, 'Concurrent delivery', 'kingshot-evidence-backed-2026-09-06-v2');

        return DB::transaction(function () use ($owner, $plan): string {
            $context = app(TerritoryPlanWriteState::class)->lock($owner->playerId, $plan->planId);

            return app(TerritoryActivityRecorder::class)->record($context, 'assigned', 'fixture-1', [$owner->playerId]);
        });
    }
}
