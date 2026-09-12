<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\AnnouncementBroadcastSource;
use App\Contexts\Alliance\Content\ValueObjects\BroadcastPageResult;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Queries\AllianceMemberAudienceQuery;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/** Durable bounded fan-out; each recipient's current facts and progress share a transaction. */
final readonly class QueueAnnouncementBroadcastRun
{
    public function __construct(
        private AllianceMemberAudienceQuery $audience,
        private PlayerReferenceQuery $players,
        private KingdomReferenceQuery $kingdoms,
        private AllianceAuthorization $authority,
        private AnnouncementBroadcastSource $source,
        private NotificationDeliveryService $deliveries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $runId, int $limit = 25): BroadcastPageResult
    {
        // This run-only visit lock commits before any owner locks are acquired.
        $candidate = DB::transaction(function () use ($runId): ?AnnouncementBroadcastRun {
            $run = AnnouncementBroadcastRun::query()->whereKey($runId)->lockForUpdate()->first();
            if (! $run instanceof AnnouncementBroadcastRun || $run->status !== BroadcastRunStatus::Pending) {
                return null;
            }
            $run->forceFill(['last_visited_at' => max(CarbonImmutable::now('UTC'), $run->last_visited_at)->addMicrosecond()])->save();

            return $run;
        });
        if ($candidate === null) {
            return new BroadcastPageResult(0, false);
        }
        $ids = $this->audience->page($candidate->alliance_id, $candidate->recipient_cursor,
            $candidate->recipient_upper_bound, $limit);
        $cursor = $candidate->recipient_cursor;
        $examined = 0;
        $completed = false;
        foreach ($ids === [] ? [null] : $ids as $membershipId) {
            $step = $this->advance($candidate, $cursor, $membershipId);
            $examined += (int) ($membershipId !== null);
            $completed = $step === 'complete';
            if ($step !== 'advanced') {
                break;
            }
            $cursor = $membershipId;
        }

        return new BroadcastPageResult($examined, $completed);
    }

    private function advance(AnnouncementBroadcastRun $candidate, ?string $expectedCursor, ?string $membershipId): string
    {
        return DB::transaction(function () use ($candidate, $expectedCursor, $membershipId): string {
            $alliance = Alliance::query()->whereKey($candidate->alliance_id)->sharedLock()->first();
            $active = $alliance instanceof Alliance && $alliance->status === AllianceStatus::Active;
            if ($alliance instanceof Alliance) {
                try {
                    $this->kingdoms->lockActiveShared((string) $alliance->kingdom_id);
                } catch (ModelNotFoundException) {
                    $active = false;
                }
            }
            $membership = $membershipId === null ? null : AllianceMembership::query()->whereKey($membershipId)
                ->where('alliance_id', $candidate->alliance_id)->lockForUpdate()->first();
            $player = null;
            if ($active && $membership instanceof AllianceMembership && $membership->status === MembershipStatus::Active) {
                try {
                    $player = $this->players->lockCurrentShared((string) $membership->player_id);
                } catch (ModelNotFoundException) {
                    // Deleted/merged identities consume progress without becoming recipients.
                }
            }
            $item = ContentItem::query()->whereKey($candidate->content_item_id)
                ->where('alliance_id', $candidate->alliance_id)->lockForUpdate()->first();
            $schedule = $candidate->schedule_id === null ? null : AnnouncementBroadcastSchedule::query()
                ->whereKey($candidate->schedule_id)->where('content_item_id', $candidate->content_item_id)
                ->where('alliance_id', $candidate->alliance_id)->lockForUpdate()->first();
            $run = AnnouncementBroadcastRun::query()->whereKey($candidate->id)
                ->where('alliance_id', $candidate->alliance_id)->where('content_item_id', $candidate->content_item_id)
                ->lockForUpdate()->first();
            if (! $run instanceof AnnouncementBroadcastRun || $run->status !== BroadcastRunStatus::Pending
                || $run->recipient_cursor !== $expectedCursor) {
                return 'stale';
            }
            if (! $active || ! $item instanceof ContentItem || ! $this->source->allows($run, $item, $schedule)) {
                $run->forceFill(['status' => BroadcastRunStatus::Cancelled, 'cancelled_at' => now(),
                    'cancellation_reason' => $active ? 'source-changed' : 'inactive-scope'])->save();
                $this->audit->record('content.broadcast_cancelled', null, $run, $run->alliance_id, [
                    'broadcast_run_id' => (string) $run->id, 'reason' => $run->cancellation_reason,
                    'recipient_count' => $run->recipient_count, 'delivery_count' => $run->delivery_count,
                ]);

                return 'cancelled';
            }
            if ($membershipId !== null) {
                if ($player === null || $player->userId === null || ! $alliance instanceof Alliance
                    || ! $membership instanceof AllianceMembership || $player->kingdomId !== (string) $alliance->kingdom_id
                    || ! $this->authority->allowsContext(new AllianceMutationContext($alliance, $player, $membership), AlliancePermission::View)) {
                    $run->skipped_count++;
                } else {
                    $receipt = $this->deliveries->queue(new NotificationIntent(
                        notificationType: 'alliance.announcement', recipientUserId: $player->userId, playerId: $player->playerId,
                        availableAt: CarbonImmutable::now('UTC'),
                        idempotencyKey: implode(':', ['alliance-announcement-run', $run->id, $player->playerId]),
                        title: (string) $item->title, body: mb_substr(trim((string) ($item->summary ?: $item->body)), 0, 1000),
                        actionUrl: '/alliance/content/'.rawurlencode((string) $item->slug),
                        subjectType: 'content_item', subjectId: (string) $item->id,
                        metadata: ['alliance_id' => $run->alliance_id, 'content_item_id' => $run->content_item_id,
                            'broadcast_run_id' => (string) $run->id],
                    ));
                    $run->recipient_count++;
                    $run->delivery_count += $receipt->count();
                    if ($receipt->count() === 0) {
                        $run->suppressed_count++;
                    } elseif (! $receipt->createdMessage && ! $receipt->hasCreatedDeliveries()) {
                        $run->replayed_count++;
                    }
                }
                $run->recipient_cursor = $membershipId;
            }
            if ($this->audience->page($run->alliance_id, $run->recipient_cursor, $run->recipient_upper_bound, 1) !== []) {
                $run->save();

                return 'advanced';
            }
            $run->forceFill(['status' => $run->recipient_count === 0 ? BroadcastRunStatus::Empty : BroadcastRunStatus::Queued,
                'queued_at' => now()])->save();
            $context = ['broadcast_run_id' => (string) $run->id, 'content_item_id' => $run->content_item_id,
                'schedule_id' => $run->schedule_id, 'scheduled_for' => $run->scheduled_for->toIso8601String(),
                'recipient_count' => $run->recipient_count, 'delivery_count' => $run->delivery_count,
                'skipped_count' => $run->skipped_count, 'suppressed_count' => $run->suppressed_count, 'replayed_count' => $run->replayed_count];
            $this->audit->record('content.broadcast_queued', null, $run, $run->alliance_id, $context);
            $this->outbox->record('broadcast.run.queued', $run->alliance_id, $run, $context,
                'broadcast-run:'.$run->id.':queued', 'alliance:'.$run->alliance_id);

            return 'complete';
        });
    }
}
