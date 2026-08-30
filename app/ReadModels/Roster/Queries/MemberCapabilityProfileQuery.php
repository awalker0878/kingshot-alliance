<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Queries;

use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferParticipantQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferPlanQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Queries\GovernorProgressionEvidenceSummaryQuery;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\ReadModels\EventAnalysis\Queries\EventPlayerHistoryQuery;
use App\ReadModels\Support\ReadModelTelemetry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Factual, authorized composition for one Alliance Governor. This projection
 * intentionally has no aggregate score or recommendation semantics.
 */
final readonly class MemberCapabilityProfileQuery
{
    private const HISTORY_LIMIT = 24;

    public function __construct(
        private EventAuthorization $eventAuthorization,
        private EventPlayerHistoryQuery $eventHistory,
        private TransferAuthorization $transferAuthorization,
        private TransferPlanQuery $transferPlans,
        private TransferParticipantQuery $transferParticipants,
        private TransferEligibilityQuery $transferEligibility,
        private GovernorProgressionEvidenceSummaryQuery $progressionEvidence,
        private AllianceIntelligenceAuthorization $intelligenceAuthorization,
    ) {}

    /** @return array<string,mixed> */
    public function forPlayer(
        string $actorPlayerId,
        string $allianceId,
        AllianceRosterEntry $entry,
        PlayerReference $player,
    ): array {
        $startedAt = hrtime(true);
        if (! $this->intelligenceAuthorization->allows(
            $actorPlayerId,
            $allianceId,
            IntelligencePermission::View,
        )) {
            throw new AuthorizationException;
        }
        if ((string) $entry->alliance_id !== $allianceId
            || (string) $entry->player_id !== $player->playerId) {
            throw new AuthorizationException;
        }

        $canViewEvents = $this->eventAuthorization->allows(
            $actorPlayerId,
            EventScope::Alliance,
            $allianceId,
            OperationsPermission::EventAllianceView,
        );
        $events = $canViewEvents
            ? $this->eventHistory->forPlayer($player, [
                'represented_alliance_id' => $allianceId,
                'limit' => self::HISTORY_LIMIT,
            ])
            : [];
        $occurrenceIds = array_values(array_unique(array_filter(array_map(
            static fn (array $event): string => (string) ($event['occurrenceId'] ?? ''),
            $events,
        ))));
        $canViewEvidence = $this->intelligenceAuthorization->allows(
            $actorPlayerId,
            $allianceId,
            IntelligencePermission::KingdomManage,
        );
        $evidence = $canViewEvidence
            ? ['access' => 'available', ...$this->progressionEvidence->profileSummary(
                $allianceId,
                (string) $entry->id,
            )]
            : [
                'access' => 'unavailable',
                'total' => 0,
                'pending' => 0,
                'needsReview' => 0,
                'committed' => 0,
                'failed' => 0,
                'latestAt' => null,
            ];

        $projection = [
            'eventAccess' => $canViewEvents ? 'available' : 'unavailable',
            'events' => $this->eventSummary($events),
            'bearHunt' => $this->bearHuntSummary($events),
            'rallies' => $canViewEvents
                ? $this->rallyHistory($allianceId, $player->playerId, $occurrenceIds)
                : [],
            'battleAssignments' => $canViewEvents
                ? $this->battleAssignmentHistory($allianceId, $player->playerId, $occurrenceIds)
                : [],
            'transfer' => $this->transfer($actorPlayerId, $allianceId, $player->playerId),
            'evidence' => $evidence,
        ];
        ReadModelTelemetry::record('member_capability.rendered', $startedAt, [
            'actor_player_id' => $actorPlayerId,
            'target_player_id' => $player->playerId,
            'alliance_id' => $allianceId,
            'roster_entry_id' => (string) $entry->id,
        ], [
            'event_count' => count($events),
            'rally_count' => count($projection['rallies']),
            'battle_assignment_count' => count($projection['battleAssignments']),
            'evidence_count' => (int) ($evidence['total'] ?? 0),
        ], [
            $projection['eventAccess'],
            (string) ($projection['transfer']['access'] ?? 'unavailable'),
            (string) ($evidence['access'] ?? 'unavailable'),
        ]);

        return $projection;
    }

    /**
     * @param  list<array<string,mixed>>  $events
     * @return array<string,mixed>
     */
    private function eventSummary(array $events): array
    {
        $completed = 0;
        $absent = 0;
        $excused = 0;
        $unresolved = 0;
        foreach ($events as $event) {
            $participation = is_array($event['participation'] ?? null) ? $event['participation'] : [];
            $completed += ($participation['completed'] ?? false) === true ? 1 : 0;
            $absent += ($participation['absent'] ?? false) === true ? 1 : 0;
            $excused += ($participation['excused'] ?? false) === true ? 1 : 0;
            $unresolved += ($participation['unresolved'] ?? false) === true ? 1 : 0;
        }

        return [
            'count' => count($events),
            'completed' => $completed,
            'absent' => $absent,
            'excused' => $excused,
            'unresolved' => $unresolved,
            'recent' => array_values(array_map(static fn (array $event): array => [
                'occurrenceId' => (string) ($event['occurrenceId'] ?? ''),
                'nameKey' => (string) ($event['eventType']['nameKey'] ?? ''),
                'slug' => (string) ($event['eventType']['slug'] ?? ''),
                'startsAt' => $event['startsAt'] ?? null,
                'outcome' => $event['participation']['outcome'] ?? null,
                'score' => $event['result']['score'] ?? null,
                'rank' => $event['result']['rank'] ?? null,
                'recordedAt' => $event['result']['recordedAt'] ?? null,
            ], array_slice($events, 0, 8))),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $events
     * @return array<string,mixed>
     */
    private function bearHuntSummary(array $events): array
    {
        $runs = array_values(array_filter($events, static fn (array $event): bool => ($event['eventType']['slug'] ?? null) === 'bear-hunt'));
        $recorded = array_values(array_filter($runs, static fn (array $event): bool => is_array($event['result'] ?? null)));

        return [
            'runCount' => count($runs),
            'recordedResultCount' => count($recorded),
            'latestRecorded' => isset($recorded[0]) ? [
                'occurrenceId' => (string) ($recorded[0]['occurrenceId'] ?? ''),
                'startsAt' => $recorded[0]['startsAt'] ?? null,
                'damage' => $recorded[0]['result']['score'] ?? null,
                'rank' => $recorded[0]['result']['rank'] ?? null,
                'recordedAt' => $recorded[0]['result']['recordedAt'] ?? null,
            ] : null,
        ];
    }

    /**
     * @param  list<string>  $occurrenceIds
     * @return list<array<string,mixed>>
     */
    private function rallyHistory(string $allianceId, string $playerId, array $occurrenceIds): array
    {
        if ($occurrenceIds === []) {
            return [];
        }

        return array_values(DB::table('rally_assignments as assignment')
            ->join('rally_groups as rally', 'rally.id', '=', 'assignment.rally_group_id')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'rally.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->where('event.scope', EventScope::Alliance->value)
            ->where('event.alliance_id', $allianceId)
            ->where('assignment.player_id', $playerId)
            ->whereIn('occurrence.id', $occurrenceIds)
            ->where('assignment.status', '!=', 'removed')
            ->orderByDesc('occurrence.starts_at')
            ->limit(self::HISTORY_LIMIT)
            ->get([
                'assignment.id',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at',
                'rally.name as group_name',
                'assignment.role',
                'assignment.status',
                'assignment.responded_at',
                'assignment.recorded_at',
            ])
            ->map(static fn ($row): array => [
                'id' => (string) $row->id,
                'occurrenceId' => (string) $row->occurrence_id,
                'startsAt' => (string) $row->starts_at,
                'groupName' => (string) $row->group_name,
                'role' => (string) $row->role,
                'status' => (string) $row->status,
                'respondedAt' => $row->responded_at === null ? null : (string) $row->responded_at,
                'recordedAt' => $row->recorded_at === null ? null : (string) $row->recorded_at,
            ])
            ->all());
    }

    /**
     * @param  list<string>  $occurrenceIds
     * @return list<array<string,mixed>>
     */
    private function battleAssignmentHistory(string $allianceId, string $playerId, array $occurrenceIds): array
    {
        if ($occurrenceIds === []) {
            return [];
        }

        return array_values(DB::table('event_objective_assignments as assignment')
            ->join('event_objectives as objective', 'objective.id', '=', 'assignment.objective_id')
            ->join('event_occurrences as occurrence', 'occurrence.id', '=', 'assignment.occurrence_id')
            ->join('events as event', 'event.id', '=', 'occurrence.event_id')
            ->where('event.scope', EventScope::Alliance->value)
            ->where('event.alliance_id', $allianceId)
            ->whereIn('occurrence.id', $occurrenceIds)
            ->where(static function (Builder $query) use ($playerId): void {
                $query->where('assignment.player_id', $playerId)
                    ->orWhereExists(static function (Builder $membership) use ($playerId): void {
                        $membership->selectRaw('1')
                            ->from('event_roster_members as roster_member')
                            ->whereColumn('roster_member.roster_id', 'assignment.roster_id')
                            ->where('roster_member.player_id', $playerId)
                            ->whereNotIn('roster_member.status', ['declined', 'removed']);
                    });
            })
            ->orderByDesc('occurrence.starts_at')
            ->limit(self::HISTORY_LIMIT)
            ->get([
                'assignment.id',
                'occurrence.id as occurrence_id',
                'occurrence.starts_at',
                'objective.name as objective_name',
                'objective.objective_type',
                'objective.status',
                'assignment.assigned_at',
            ])
            ->map(static fn ($row): array => [
                'id' => (string) $row->id,
                'occurrenceId' => (string) $row->occurrence_id,
                'startsAt' => (string) $row->starts_at,
                'objectiveName' => (string) $row->objective_name,
                'objectiveType' => (string) $row->objective_type,
                'status' => (string) $row->status,
                'assignedAt' => $row->assigned_at === null ? null : (string) $row->assigned_at,
            ])
            ->all());
    }

    /** @return array<string,mixed> */
    private function transfer(string $actorPlayerId, string $allianceId, string $playerId): array
    {
        if (! $this->transferAuthorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            return ['access' => 'unavailable', 'assessment' => null];
        }

        $plan = $this->transferPlans->currentForAlliance($allianceId);
        if (! $plan instanceof TransferPlan) {
            return ['access' => 'available', 'assessment' => null];
        }
        $participant = $this->transferParticipants->forPlan($allianceId, (string) $plan->id)
            ->first(static fn (TransferParticipant $candidate): bool => (string) $candidate->player_id === $playerId);
        if (! $participant instanceof TransferParticipant) {
            return ['access' => 'available', 'assessment' => null];
        }
        $evaluation = $this->transferEligibility->forPlan(
            $allianceId,
            $plan,
            collect([$participant]),
        )[(string) $participant->id] ?? null;
        $assessment = is_array($evaluation) ? ($evaluation['assessment'] ?? null) : null;

        return [
            'access' => 'available',
            'assessment' => ! $assessment instanceof TransferEligibilityAssessment ? null : [
                'participantId' => (string) $participant->id,
                'planId' => (string) $plan->id,
                'direction' => $participant->direction->value,
                'readinessState' => $participant->readiness_state->value,
                'outcome' => $assessment->outcome->value,
                'evaluatedAt' => $assessment->evaluatedAt->toIso8601String(),
                'primaryAction' => $assessment->primaryAction,
                'requirements' => array_values(array_map(static fn ($requirement): array => [
                    'key' => $requirement->key->value,
                    'state' => $requirement->state->value,
                    'sourceReference' => $requirement->sourceReference,
                    'observedAt' => $requirement->observedAt?->toIso8601String(),
                    'validUntil' => $requirement->validUntil?->toIso8601String(),
                ], $assessment->requirements)),
            ],
        ];
    }
}
