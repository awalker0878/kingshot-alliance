<?php

declare(strict_types=1);

namespace App\ReadModels\CommandOverview\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Events\Services\EventTypeProfileResolver;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\ReadModels\AllianceGovernance\Queries\AllianceRosterReconciliationQuery;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use App\ReadModels\Roster\Services\RosterIntelligence;
use App\ReadModels\Support\ReadModelTelemetry;
use App\ReadModels\TerritoryPlanning\Queries\TerritoryReconciliationQuery;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Recomputable R4/R5 attention surface composed from authorized owner reads.
 *
 * This projection stores no generic task, score or attention state. A missing
 * owner permission suppresses that owner's item and count before retrieval.
 */
final readonly class AllianceCommandQuery
{
    public function __construct(
        private AllianceAuthorization $allianceAuthorization,
        private AllianceIntelligenceAuthorization $intelligenceAuthorization,
        private TransferAuthorization $transferAuthorization,
        private EventCalendarQuery $events,
        private EventTypeProfileResolver $profiles,
        private EventCommandQuery $eventCommand,
        private RosterIntelligence $roster,
        private TransferPlanQuery $transferPlans,
        private TransferParticipantQuery $transferParticipants,
        private TransferEligibilityQuery $transferEligibility,
        private TerritoryPlanQuery $territoryPlans,
        private TerritoryReconciliationQuery $territoryReconciliation,
        private AllianceRosterReconciliationQuery $rosterReconciliation,
    ) {}

    /**
     * @param  list<array<string,mixed>>  $authorizedIntelligenceSignals
     * @return array{asOf:string,actionCount:int,items:list<array<string,mixed>>}|null
     */
    public function for(
        int $userId,
        PlayerReference $actor,
        string $allianceId,
        array $authorizedIntelligenceSignals = [],
    ): ?array {
        $startedAt = hrtime(true);
        if ($actor->userId !== $userId || ! $this->allianceAuthorization->allows(
            $actor->playerId,
            $allianceId,
            AlliancePermission::MembershipManage,
        )) {
            ReadModelTelemetry::record('alliance_command.rendered', $startedAt, [
                'user_id' => $userId,
                'actor_player_id' => $actor->playerId,
                'alliance_id' => $allianceId,
            ], ['item_count' => 0, 'action_count' => 0], ['permission_denied']);

            return null;
        }

        $items = [];
        $event = $this->event($actor, $allianceId);
        if ($event !== null) {
            $items[] = $event;
        }

        $canViewIntelligence = $this->intelligenceAuthorization->allows(
            $actor->playerId,
            $allianceId,
            IntelligencePermission::View,
        );
        if ($canViewIntelligence) {
            $items[] = $this->roster($allianceId);
            if ($authorizedIntelligenceSignals !== []) {
                $items[] = $this->item(
                    code: 'intelligence_changes',
                    owner: 'intelligence.signals',
                    state: 'changed',
                    reasonKey: 'application.dashboard.commandReasons.intelligenceChanges',
                    count: count($authorizedIntelligenceSignals),
                    href: '/alliance/kingdom-alliances/intelligence',
                    observedAt: $this->latestTimestamp($authorizedIntelligenceSignals, 'observedAt'),
                    actionable: false,
                );
            }

            $territory = $this->territory($actor, $allianceId);
            if ($territory !== null) {
                $items[] = $territory;
            }
        }

        if ($this->intelligenceAuthorization->allows(
            $actor->playerId,
            $allianceId,
            IntelligencePermission::KingdomManage,
        )) {
            $evidence = $this->evidence($allianceId);
            if ($evidence !== null) {
                $items[] = $evidence;
            }

            $reconciliation = $this->rosterReconciliationAttention($allianceId);
            if ($reconciliation !== null) {
                $items[] = $reconciliation;
            }
        }

        if ($this->transferAuthorization->allows(
            $actor->playerId,
            $allianceId,
            TransferPermission::View,
        )) {
            $transfer = $this->transfer($allianceId);
            if ($transfer !== null) {
                $items[] = $transfer;
            }
        }

        if ($this->allianceAuthorization->allows(
            $actor->playerId,
            $allianceId,
            AlliancePermission::RecruitmentManage,
        )) {
            $recruitmentReview = $this->recruitmentReentryReview($allianceId);
            if ($recruitmentReview !== null) {
                $items[] = $recruitmentReview;
            }
        }

        $communications = $this->communications($userId, $actor->playerId);
        if ($communications !== null) {
            $items[] = $communications;
        }

        usort($items, static function (array $left, array $right): int {
            $action = ((int) ($right['actionable'] ?? false)) <=> ((int) ($left['actionable'] ?? false));
            if ($action !== 0) {
                return $action;
            }

            $count = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));

            return $count !== 0 ? $count : strcmp((string) $left['code'], (string) $right['code']);
        });

        $projection = [
            'asOf' => now()->toIso8601String(),
            'actionCount' => array_sum(array_map(
                static fn (array $item): int => ($item['actionable'] ?? false) === true
                    ? max(1, (int) ($item['count'] ?? 0))
                    : 0,
                $items,
            )),
            'items' => $items,
        ];
        ReadModelTelemetry::record('alliance_command.rendered', $startedAt, [
            'user_id' => $userId,
            'actor_player_id' => $actor->playerId,
            'alliance_id' => $allianceId,
        ], [
            'item_count' => count($items),
            'action_count' => $projection['actionCount'],
        ], array_map(
            static fn (array $item): string => (string) ($item['code'] ?? ''),
            $items,
        ));

        return $projection;
    }

    /** @return array<string,mixed>|null */
    private function event(PlayerReference $actor, string $allianceId): ?array
    {
        $occurrences = $this->events->forAlliance($actor, $allianceId, futureDays: 30);
        foreach ($occurrences as $occurrence) {
            if (! $occurrence instanceof EventOccurrence || ! $occurrence->event instanceof Event) {
                continue;
            }

            $profile = $this->profiles->resolve($occurrence->event->eventType);
            if ($profile['profile_enabled'] !== true
                || ! in_array(
                    EventWorkflowDimension::ReadinessCloseout->value,
                    $profile['workflow_dimensions'],
                    true,
                )) {
                continue;
            }

            $command = $this->eventCommand->forEvent($actor, $occurrence->event, (string) $occurrence->id);
            $blockers = (int) ($command['blockerCount'] ?? 0);
            $warnings = (int) ($command['warningCount'] ?? 0);

            return $this->item(
                code: 'next_event',
                owner: 'operations.events',
                state: (string) ($command['state'] ?? 'unknown'),
                reasonKey: $blockers > 0
                    ? 'application.dashboard.commandReasons.eventBlockers'
                    : 'application.dashboard.commandReasons.nextEvent',
                count: $blockers,
                href: '/events/'.(string) $occurrence->event->id.'/manage?occurrence='.(string) $occurrence->id,
                observedAt: $occurrence->starts_at->toIso8601String(),
                actionable: $blockers > 0,
                metadata: [
                    'eventId' => (string) $occurrence->event->id,
                    'occurrenceId' => (string) $occurrence->id,
                    'title' => $occurrence->event->title,
                    'nameKey' => (string) $occurrence->event->eventType->name_key,
                    'canonicalKey' => (string) $occurrence->event->eventType->slug,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'warningCount' => $warnings,
                    'facts' => $this->eventFacts($command),
                ],
            );
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function roster(string $allianceId): array
    {
        $projection = $this->roster->forAlliance($allianceId);
        $quality = is_array($projection['snapshotQuality'] ?? null)
            ? $projection['snapshotQuality']
            : [];
        $stale = (int) ($quality['stale'] ?? 0);
        $missing = (int) ($quality['missing'] ?? 0);
        $count = $stale + $missing;

        return $this->item(
            code: 'governor_observation_freshness',
            owner: 'intelligence.roster',
            state: $count > 0 ? 'needs_attention' : 'current',
            reasonKey: $count > 0
                ? 'application.dashboard.commandReasons.rosterStaleOrMissing'
                : 'application.dashboard.commandReasons.rosterCurrent',
            count: $count,
            href: '/alliance/roster/intelligence',
            observedAt: is_string($projection['asOf'] ?? null) ? $projection['asOf'] : null,
            actionable: $count > 0,
            metadata: [
                'stale' => $stale,
                'missing' => $missing,
                'staleAfterDays' => (int) ($quality['staleAfterDays'] ?? 0),
            ],
        );
    }

    /** @return array<string,mixed>|null */
    private function rosterReconciliationAttention(string $allianceId): ?array
    {
        $projection = $this->rosterReconciliation->forAlliance($allianceId);
        $batch = is_array($projection['batch'] ?? null) ? $projection['batch'] : [];
        $summary = is_array($projection['summary'] ?? null) ? $projection['summary'] : [];
        $needsReview = (int) ($summary['needsReview'] ?? 0);
        if ($batch === [] || $needsReview === 0) {
            return null;
        }

        return $this->item(
            code: 'roster_reconciliation_required',
            owner: 'intelligence.roster',
            state: 'needs_attention',
            reasonKey: 'application.dashboard.commandReasons.rosterReconciliationRequired',
            count: $needsReview,
            href: '/alliance/roster/reconciliation',
            observedAt: is_string($batch['capturedAt'] ?? null) ? $batch['capturedAt'] : null,
            actionable: true,
            metadata: [
                'batchId' => $batch['id'] ?? null,
                'completeRoster' => (bool) ($batch['completeRoster'] ?? false),
                'summary' => $summary,
            ],
        );
    }

    /** @return array<string,mixed>|null */
    private function recruitmentReentryReview(string $allianceId): ?array
    {
        $query = RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('merged_into_id')
            ->whereNull('anonymized_at')
            ->where('reentry_control', RecruitmentReentryControl::ReviewRequired->value)
            ->where(static fn (Builder $builder) => $builder
                ->whereNull('reentry_review_at')
                ->orWhere('reentry_review_at', '<=', now()));
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        $latest = (clone $query)->orderByDesc('reentry_set_at')->orderByDesc('id')->first();

        return $this->item(
            code: 'recruitment_reentry_review_due',
            owner: 'alliance.recruitment',
            state: 'needs_attention',
            reasonKey: 'application.dashboard.commandReasons.recruitmentReentryReviewDue',
            count: $count,
            href: '/alliance/recruitment',
            observedAt: $latest?->reentry_set_at?->toIso8601String(),
            actionable: true,
        );
    }

    /** @return array<string,mixed>|null */
    private function transfer(string $allianceId): ?array
    {
        $plan = $this->transferPlans->currentForAlliance($allianceId);
        if ($plan === null) {
            return null;
        }

        $participants = $this->transferParticipants->forPlan($allianceId, (string) $plan->id);
        if (! $plan->window instanceof TransferWindow) {
            return $this->item(
                code: 'transfer_verification',
                owner: 'game_world.kingdom_transfers',
                state: 'missing_window',
                reasonKey: 'application.dashboard.commandReasons.transferWindowMissing',
                count: max(1, $participants->count()),
                href: '/alliance/transfers/manage',
                observedAt: $plan->updated_at?->toIso8601String(),
                actionable: true,
                metadata: ['planId' => (string) $plan->id],
            );
        }

        $assessments = $this->transferEligibility->forPlan($allianceId, $plan, $participants);
        $affected = [];
        foreach ($participants as $participant) {
            if (! $participant instanceof TransferParticipant) {
                continue;
            }

            $assessment = $assessments[(string) $participant->id]['assessment'] ?? null;
            $requiresAttention = $participant->readiness_state === TransferReadinessState::Blocked
                || ($assessment instanceof TransferEligibilityAssessment && in_array($assessment->outcome, [
                    TransferEligibilityOutcome::Blocked,
                    TransferEligibilityOutcome::NeedsVerification,
                ], true));
            if ($requiresAttention) {
                $affected[] = (string) $participant->id;
            }
        }

        return $this->item(
            code: 'transfer_verification',
            owner: 'game_world.kingdom_transfers',
            state: $affected === [] ? 'verified' : 'needs_attention',
            reasonKey: $affected === []
                ? 'application.dashboard.commandReasons.transferVerified'
                : 'application.dashboard.commandReasons.transferNeedsVerification',
            count: count($affected),
            href: '/alliance/transfers/readiness',
            observedAt: $plan->updated_at?->toIso8601String(),
            actionable: $affected !== [],
            affectedIds: $affected,
            metadata: ['planId' => (string) $plan->id],
        );
    }

    /** @return array<string,mixed>|null */
    private function territory(PlayerReference $actor, string $allianceId): ?array
    {
        $plans = $this->territoryPlans->visiblePlans($actor->playerId, $actor->kingdomId);
        $published = null;
        foreach ($plans as $plan) {
            if (($plan['status'] ?? null) === TerritoryPlanStatus::Published->value) {
                $published = $plan;
                break;
            }
        }
        if (! is_array($published) || ! is_string($published['id'] ?? null)) {
            return null;
        }

        try {
            $reconciliation = $this->territoryReconciliation->build(
                $actor->playerId,
                $published['id'],
                allianceId: $allianceId,
            );
        } catch (RuntimeException) {
            return $this->item(
                code: 'territory_reconciliation',
                owner: 'operations.territory_planning',
                state: 'unavailable',
                reasonKey: 'application.dashboard.commandReasons.territoryUnavailable',
                count: 0,
                href: '/territory/'.$published['id'].'/reconciliation',
                observedAt: is_string($published['updated_at'] ?? null) ? $published['updated_at'] : null,
                actionable: false,
                metadata: ['planId' => $published['id']],
            );
        }

        $state = (string) ($reconciliation['state'] ?? 'unavailable');
        $summary = is_array($reconciliation['summary'] ?? null) ? $reconciliation['summary'] : [];
        $count = $state === 'ready' ? (int) ($summary['out_of_position'] ?? 0)
            + (int) ($summary['missing'] ?? 0)
            + (int) ($summary['unexpected'] ?? 0)
            + (int) ($summary['structures_changed'] ?? 0) : 0;

        return $this->item(
            code: 'territory_reconciliation',
            owner: 'operations.territory_planning',
            state: $state === 'ready' && $count > 0 ? 'needs_attention' : $state,
            reasonKey: match (true) {
                $state === 'ready' && $count > 0 => 'application.dashboard.commandReasons.territoryDifferences',
                $state === 'ready' => 'application.dashboard.commandReasons.territoryAligned',
                default => 'application.dashboard.commandReasons.territoryObservationMissing',
            },
            count: $count,
            href: '/territory/'.$published['id'].'/reconciliation',
            observedAt: is_string($reconciliation['observation']['captured_at'] ?? null)
                ? $reconciliation['observation']['captured_at']
                : (is_string($published['updated_at'] ?? null) ? $published['updated_at'] : null),
            actionable: $count > 0,
            metadata: ['planId' => $published['id'], 'summary' => $summary],
        );
    }

    /** @return array<string,mixed>|null */
    private function evidence(string $allianceId): ?array
    {
        $counts = GameEvidence::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('lifecycle_status', [
                EvidenceLifecycleStatus::NeedsReview->value,
                EvidenceLifecycleStatus::Failed->value,
            ])
            ->selectRaw('lifecycle_status, count(*) as aggregate')
            ->groupBy('lifecycle_status')
            ->pluck('aggregate', 'lifecycle_status');
        $review = (int) ($counts[EvidenceLifecycleStatus::NeedsReview->value] ?? 0);
        $failed = (int) ($counts[EvidenceLifecycleStatus::Failed->value] ?? 0);
        if ($review + $failed === 0) {
            return null;
        }

        return $this->item(
            code: 'evidence_review',
            owner: 'intelligence.evidence',
            state: 'needs_attention',
            reasonKey: 'application.dashboard.commandReasons.evidenceReview',
            count: $review + $failed,
            href: '/alliance/roster/intelligence',
            actionable: true,
            metadata: ['needsReview' => $review, 'failed' => $failed],
        );
    }

    /** @return array<string,mixed>|null */
    private function communications(int $userId, string $playerId): ?array
    {
        $messageIds = NotificationMessage::query()
            ->select('id')
            ->where('recipient_user_id', $userId)
            ->where(static fn (Builder $builder) => $builder
                ->whereNull('player_id')
                ->orWhere('player_id', $playerId));

        $query = NotificationDelivery::query()
            ->whereIn('notification_message_id', $messageIds)
            ->where('status', DeliveryStatus::Failed->value)
            ->where('channel', '!=', DeliveryChannel::InApp->value);
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        $latest = (clone $query)->orderByDesc('failed_at')->orderByDesc('id')->first();

        return $this->item(
            code: 'communications_failures',
            owner: 'communications.delivery',
            state: 'failed',
            reasonKey: 'application.dashboard.commandReasons.communicationsFailed',
            count: $count,
            href: '/notifications',
            observedAt: $latest?->failed_at?->toIso8601String(),
            actionable: true,
        );
    }

    /**
     * @param  list<string>  $affectedIds
     * @param  array<string,mixed>  $metadata
     * @return array<string,mixed>
     */
    private function item(
        string $code,
        string $owner,
        string $state,
        string $reasonKey,
        int $count,
        string $href,
        ?string $observedAt = null,
        bool $actionable = false,
        array $affectedIds = [],
        array $metadata = [],
    ): array {
        return [
            'code' => $code,
            'owner' => $owner,
            'state' => $state,
            'reasonKey' => $reasonKey,
            'count' => max(0, $count),
            'observedAt' => $observedAt,
            'actionable' => $actionable,
            'affectedIds' => array_values(array_unique($affectedIds)),
            'handoff' => ['href' => $href],
            'metadata' => $metadata,
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private function latestTimestamp(array $rows, string $field): ?string
    {
        $timestamps = array_values(array_filter(array_map(
            static fn (array $row): ?string => is_string($row[$field] ?? null) ? $row[$field] : null,
            $rows,
        )));
        rsort($timestamps, SORT_STRING);

        return $timestamps[0] ?? null;
    }

    /** @param array<string,mixed> $command
     * @return list<array<string,mixed>>
     */
    private function eventFacts(array $command): array
    {
        $facts = [];
        foreach (is_array($command['sections'] ?? null) ? $command['sections'] : [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $item) {
                if (is_array($item)) {
                    $facts[] = $item;
                }
            }
        }

        return $facts;
    }
}
