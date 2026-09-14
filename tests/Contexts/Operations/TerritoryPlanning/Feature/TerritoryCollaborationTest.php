<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Actions\CommentOnTerritoryObject;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Actions\GrantTerritoryPlanAccess;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\ReviewTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RevokeTerritoryPlanAccess;
use App\Contexts\Operations\TerritoryPlanning\Actions\RevokeTerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryActivity;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryObjectComment;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAccessGrant;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanReview;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryCollaborationQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryNotificationEligibilityQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritorySharedRevisionQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryActivityRecorder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use App\Workflows\NotificationDelivery\Actions\QueueTerritoryNotifications;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_is_hashed_filtered_immutable_and_revocable(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        $published = app(PublishTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, app(TerritoryPlanSnapshotBuilder::class)->checksum($this->snapshot($plan)));
        $share = app(CreateTerritoryShare::class)->handle($owner->playerId, $plan->id, $published->publishedRevisionId, $member->playerId, ['owner'], CarbonImmutable::now()->addDay());
        $stored = TerritoryShare::query()->findOrFail($share['id']);
        self::assertNotSame($share['token'], $stored->token_hash);
        self::assertSame(hash('sha256', $share['token']), $stored->token_hash);
        self::assertArrayNotHasKey('token_hash', $stored->toArray());
        $result = app(TerritorySharedRevisionQuery::class)->get($member->playerId, $share['id'], $share['token']);
        self::assertSame('kingshot-evidence-backed-2026-09-06-v2', $result['map']['id']);
        self::assertSame($result['snapshot']['plan']['map_dataset_checksum'], $result['map']['checksum']);
        self::assertSame(['owner'], array_column($result['snapshot']['alliances'], 'key'));
        self::assertSame(['city'], array_column($result['snapshot']['objects'], 'key'));
        self::assertArrayNotHasKey('planning_preferences', $result['snapshot']['plan']);
        $snapshot = $this->snapshot($plan);
        $snapshot['objects'][0]['x'] = 140;
        app(SaveTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, (string) Str::uuid(), $snapshot['alliances'], $snapshot['groups'], $snapshot['objects'], []);
        self::assertSame(100, app(TerritorySharedRevisionQuery::class)->get($member->playerId, $share['id'], $share['token'])['snapshot']['objects'][0]['x']);
        app(RevokeTerritoryShare::class)->handle($owner->playerId, $plan->id, $share['id']);
        $this->expectException(AuthorizationException::class);
        app(TerritorySharedRevisionQuery::class)->get($member->playerId, $share['id'], $share['token']);
    }

    public function test_share_access_rechecks_current_kingdom_role_and_never_accepts_another_actor(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        $published = app(PublishTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, app(TerritoryPlanSnapshotBuilder::class)->checksum($this->snapshot($plan)));
        $share = app(CreateTerritoryShare::class)->handle($owner->playerId, $plan->id, $published->publishedRevisionId, $member->playerId, ['owner'], CarbonImmutable::now()->addDay());
        try {
            app(TerritorySharedRevisionQuery::class)->get($owner->playerId, $share['id'], $share['token']);
            self::fail('The owner cannot impersonate the recipient of a private share.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $assignment = KingdomRoleAssignment::query()->where('player_id', $member->playerId)->whereNull('revoked_at')->firstOrFail();
        app(RemoveKingdomRole::class)->handle($owner->playerId, $owner->kingdomId, (string) $assignment->id);
        $this->expectException(AuthorizationException::class);
        app(TerritorySharedRevisionQuery::class)->get($member->playerId, $share['id'], $share['token']);
    }

    public function test_expired_share_and_corrupt_publication_fail_closed(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        $published = app(PublishTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, app(TerritoryPlanSnapshotBuilder::class)->checksum($this->snapshot($plan)));
        $share = app(CreateTerritoryShare::class)->handle($owner->playerId, $plan->id, $published->publishedRevisionId, $member->playerId, ['owner'], CarbonImmutable::now()->addMinute());
        $this->travel(2)->minutes();
        try {
            app(TerritorySharedRevisionQuery::class)->get($member->playerId, $share['id'], $share['token']);
            self::fail('Expired shares must be denied.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        $this->travelBack();
        TerritoryPlanRevision::query()->whereKey($published->publishedRevisionId)->update(['snapshot_checksum' => str_repeat('0', 64)]);
        $this->expectException(AuthorizationException::class);
        app(TerritorySharedRevisionQuery::class)->get($member->playerId, $share['id'], $share['token']);
    }

    public function test_comment_keeps_historical_object_and_review_becomes_stale_after_saved_edit(): void
    {
        [$owner, , $plan] = $this->scenario();
        $commentId = app(CommentOnTerritoryObject::class)->handle($owner->playerId, $plan->id, $plan->revision, 'city', 'Keep room for this Governor.');
        $checksum = app(TerritoryCollaborationQuery::class)->get($owner->playerId, $plan->id)['current_snapshot_checksum'];
        $review = app(ReviewTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, $checksum, 'approved');
        self::assertSame($review, app(ReviewTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, $checksum, 'approved'));
        self::assertSame(1, TerritoryActivity::query()->where('kind', 'reviewed')->count());
        $snapshot = $this->snapshot($plan);
        $snapshot['objects'] = array_values(array_filter($snapshot['objects'], static fn (array $row): bool => $row['key'] !== 'city'));
        app(SaveTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, (string) Str::uuid(), $snapshot['alliances'], $snapshot['groups'], $snapshot['objects'], []);
        self::assertSame(100, TerritoryObjectComment::query()->findOrFail($commentId)->object_snapshot['x']);
        self::assertTrue(app(TerritoryCollaborationQuery::class)->get($owner->playerId, $plan->id)['reviews'][0]['stale']);
        $this->expectException(ValidationException::class);
        app(ReviewTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->fresh()->revision, $checksum, 'approved');
    }

    public function test_review_grant_allows_its_object_but_cannot_approve_ungranted_layers_and_revocation_is_immediate(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        $grant = app(GrantTerritoryPlanAccess::class)->handle($owner->playerId, $plan->id, $member->playerId, 'owner', 'review', CarbonImmutable::now()->addDay());
        $comment = app(CommentOnTerritoryObject::class)->handle($member->playerId, $plan->id, $plan->revision, 'city', 'Position checked.');
        self::assertNotEmpty($comment);
        $checksum = app(TerritoryCollaborationQuery::class)->get($owner->playerId, $plan->id)['current_snapshot_checksum'];
        try {
            app(ReviewTerritoryPlan::class)->handle($member->playerId, $plan->id, $plan->revision, $checksum, 'approved');
            self::fail('A single layer grant cannot approve a multi-Alliance plan.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }
        app(RevokeTerritoryPlanAccess::class)->handle($owner->playerId, $plan->id, $grant);
        $this->expectException(AuthorizationException::class);
        app(CommentOnTerritoryObject::class)->handle($member->playerId, $plan->id, $plan->revision, 'city', 'A revoked comment.');
    }

    public function test_layer_editor_can_change_only_granted_objects_and_never_whole_plan_preferences(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        app(GrantTerritoryPlanAccess::class)->handle($owner->playerId, $plan->id, $member->playerId, 'owner', 'edit', CarbonImmutable::now()->addDay());
        $snapshot = $this->snapshot($plan);
        $snapshot['objects'][0]['x'] = 102;
        $saved = app(SaveTerritoryPlan::class)->handle($member->playerId, $plan->id, $plan->revision,
            (string) Str::uuid(), $snapshot['alliances'], $snapshot['groups'], $snapshot['objects'], $snapshot['plan']['planning_preferences']);
        self::assertSame(102, $saved->snapshot['objects'][0]['x']);
        $snapshot['objects'][1]['x'] = 204;
        $this->expectException(AuthorizationException::class);
        app(SaveTerritoryPlan::class)->handle($member->playerId, $plan->id, $saved->revision,
            (string) Str::uuid(), $snapshot['alliances'], $snapshot['groups'], $snapshot['objects'], $snapshot['plan']['planning_preferences']);
    }

    public function test_notification_pages_retry_idempotently_and_reauthorize_revoked_review_requests(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        $grant = app(GrantTerritoryPlanAccess::class)->handle($owner->playerId, $plan->id, $member->playerId, 'owner', 'review', CarbonImmutable::now()->addDay());
        $activity = TerritoryActivity::query()->where('kind', 'review_requested')->firstOrFail();
        $first = app(QueueTerritoryNotifications::class)->handle(1, 1);
        self::assertSame(1, $first['queued']);
        self::assertSame(0, app(QueueTerritoryNotifications::class)->handle(1, 1)['queued']);
        $activity->forceFill(['after_player_id' => null, 'completed_at' => null])->save();
        self::assertSame(0, app(QueueTerritoryNotifications::class)->handle(1, 1)['queued']);
        self::assertSame(1, NotificationMessage::query()->where('notification_type', 'territory.activity')->count());
        app(RevokeTerritoryPlanAccess::class)->handle($owner->playerId, $plan->id, $grant);
        $source = new NotificationSource('territory.activity', $member->userId, $member->playerId, 'territory_activity', $activity->id, ['plan_id' => $plan->id]);
        self::assertFalse(app(TerritoryNotificationEligibilityQuery::class)->allows($source, $member));
    }

    public function test_collaboration_collections_page_at_hard_limits_without_duplicates(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        $published = app(PublishTerritoryPlan::class)->handle(
            $owner->playerId,
            $plan->id,
            $plan->revision,
            app(TerritoryPlanSnapshotBuilder::class)->checksum($this->snapshot($plan)),
        );
        $now = now();
        for ($index = 0; $index < 51; $index++) {
            TerritoryObjectComment::query()->create([
                'territory_plan_id' => $plan->id,
                'object_key' => 'city',
                'alliance_key' => 'owner',
                'author_player_id' => $owner->playerId,
                'head_revision' => $plan->revision,
                'object_snapshot' => ['key' => 'city', 'x' => 100, 'y' => 100],
                'body' => 'comment-'.$index,
            ]);
            TerritoryPlanReview::query()->create([
                'territory_plan_id' => $plan->id,
                'reviewer_player_id' => $owner->playerId,
                'head_revision' => $index + 100,
                'snapshot_checksum' => hash('sha256', 'review-'.$index),
                'decision' => 'approved',
                'note' => null,
            ]);
            TerritoryShare::query()->create([
                'territory_plan_id' => $plan->id,
                'territory_plan_revision_id' => $published->publishedRevisionId,
                'recipient_player_id' => $member->playerId,
                'created_by_player_id' => $owner->playerId,
                'token_hash' => hash('sha256', 'share-'.$index),
                'alliance_keys' => ['owner'],
                'expires_at' => $now->copy()->addDay(),
            ]);
        }
        for ($index = 0; $index < 101; $index++) {
            TerritoryPlanAccessGrant::query()->create([
                'territory_plan_id' => $plan->id,
                'player_id' => $member->playerId,
                'granted_by_player_id' => $owner->playerId,
                'alliance_key' => 'layer-'.$index,
                'permission' => 'review',
                'expires_at' => $now->copy()->addDay(),
            ]);
        }

        $query = app(TerritoryCollaborationQuery::class);
        $first = $query->get($owner->playerId, $plan->id);
        self::assertCount(50, $first['comments']);
        self::assertCount(50, $first['reviews']);
        self::assertCount(100, $first['grants']);
        self::assertCount(50, $first['shares']);
        self::assertNotNull($first['comments_after']);
        self::assertNotNull($first['reviews_before']);
        self::assertNotNull($first['grants_after']);
        self::assertNotNull($first['shares_before']);

        $second = $query->get(
            $owner->playerId,
            $plan->id,
            $first['comments_after'],
            $first['reviews_before'],
            $first['grants_after'],
            $first['shares_before'],
        );
        self::assertCount(1, $second['comments']);
        self::assertCount(1, $second['reviews']);
        self::assertCount(1, $second['grants']);
        self::assertCount(1, $second['shares']);
        foreach (['comments', 'reviews', 'grants', 'shares'] as $collection) {
            $firstIds = array_column($first[$collection], 'id');
            $secondIds = array_column($second[$collection], 'id');
            self::assertSame([], array_values(array_intersect($firstIds, $secondIds)));
        }
    }

    public function test_review_and_activity_roll_back_together_when_audit_fails(): void
    {
        [$owner, , $plan] = $this->scenario();
        $checksum = app(TerritoryCollaborationQuery::class)->get($owner->playerId, $plan->id)['current_snapshot_checksum'];
        $fail = true;
        DB::listen(static function (QueryExecuted $query) use (&$fail): void {
            if ($fail && str_starts_with($query->sql, 'insert into "audit_events"')) {
                $fail = false;
                throw new RuntimeException('Audit failure fixture.');
            }
        });
        try {
            app(ReviewTerritoryPlan::class)->handle($owner->playerId, $plan->id, $plan->revision, $checksum, 'approved');
            self::fail('Audit failure must abort review.');
        } catch (RuntimeException $exception) {
            self::assertSame('Audit failure fixture.', $exception->getMessage());
        }
        self::assertDatabaseCount('territory_plan_reviews', 0);
        self::assertSame(0, TerritoryActivity::query()->where('kind', 'reviewed')->count());
    }

    public function test_assignment_activity_only_records_changed_recipients_and_batches_its_input(): void
    {
        [$owner, $member, $plan] = $this->scenario();
        DB::transaction(function () use ($owner, $member, $plan): void {
            $context = app(TerritoryPlanWriteState::class)->lock($owner->playerId, $plan->id);
            $unchanged = [['key' => 'same', 'player_id' => $owner->playerId]];
            $objects = [...$unchanged, ['key' => 'new', 'player_id' => $member->playerId], ['key' => 'also-new', 'player_id' => $member->playerId]];
            app(TerritoryActivityRecorder::class)->recordAssignments($context, $unchanged, $objects);
            app(TerritoryActivityRecorder::class)->recordAssignments($context, $unchanged, $objects);
        });
        self::assertSame(1, TerritoryActivity::query()->where('kind', 'assigned')->count());
        self::assertSame([$member->playerId], TerritoryActivity::query()->where('kind', 'assigned')->firstOrFail()->recipient_player_ids);
    }

    /** @return array{PlayerReference,PlayerReference,TerritoryPlan} */
    private function scenario(): array
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player((int) $factory->authUser()->id, 61377);
        $member = $factory->player((int) $factory->authUser()->id, 61377);
        $alliance = $factory->alliance($owner);
        AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $member->playerId,
            'rank' => AllianceRank::R1, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $roles = app(BootstrapKingdomAdministrator::class)->handle($owner->kingdomId, $owner->playerId);
        app(AssignKingdomRole::class)->handle($owner->playerId, $owner->kingdomId, $member->playerId, $roles->viewerRoleId);
        $created = app(CreateTerritoryPlan::class)->handle($owner->playerId, TerritoryPlanScope::Kingdom, $owner->kingdomId, null, 'Collaboration', 'kingshot-evidence-backed-2026-09-06-v2');
        app(SaveTerritoryPlan::class)->handle($owner->playerId, $created->planId, 1, (string) Str::uuid(), [
            ['key' => 'owner', 'alliance_id' => $alliance->allianceId, 'display_name' => $alliance->name, 'presentation_color' => '#225577'],
            ['key' => 'external', 'external_name' => 'External planning identity', 'display_name' => 'External planning identity', 'presentation_color' => '#772255'],
        ], [], [
            ['key' => 'city', 'type' => 'governor_city', 'alliance_key' => 'owner', 'x' => 100, 'y' => 100],
            ['key' => 'private-city', 'type' => 'governor_city', 'alliance_key' => 'external', 'x' => 200, 'y' => 200],
        ]);

        return [$owner, $member, TerritoryPlan::query()->findOrFail($created->planId)];
    }

    /** @return array<string,mixed> */
    private function snapshot(TerritoryPlan $plan): array
    {
        return app(TerritoryPlanSnapshotBuilder::class)->build($plan);
    }
}
