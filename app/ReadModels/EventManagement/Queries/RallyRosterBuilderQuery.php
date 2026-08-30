<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Queries;

use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\ReadModels\Roster\Queries\PlayerSnapshotQuery;
use App\ReadModels\Support\ReadModelTelemetry;
use Carbon\CarbonImmutable;

/**
 * Authorized read-side composition for factual Rally planning gaps.
 *
 * Operations owners retain every assignment/registration/roster fact. This
 * projection is recomputed for presentation and never ranks Governors or
 * recommends a lineup.
 */
final readonly class RallyRosterBuilderQuery
{
    public function __construct(
        private AllianceIntelligenceAuthorization $intelligenceAuthorization,
        private PlayerSnapshotQuery $snapshots,
    ) {}

    /**
     * @param  list<array<string,mixed>>  $rallyOperations
     * @param  list<array<string,mixed>>  $participants
     * @param  list<array<string,mixed>>  $rosterOperations
     * @return list<array<string,mixed>>
     */
    public function forEvent(
        string $actorPlayerId,
        Event $event,
        array $rallyOperations,
        array $participants,
        array $rosterOperations,
    ): array {
        $startedAt = hrtime(true);
        $playerIds = $this->playerIds($rallyOperations, $participants, $rosterOperations);
        $observations = $this->observationStates($actorPlayerId, $event, $playerIds);
        $participantsByOccurrence = collect($participants)->groupBy('occurrenceId');
        $rostersByOccurrence = collect($rosterOperations)->keyBy('occurrenceId');

        $projection = array_values(array_map(function (array $operation) use (
            $participantsByOccurrence,
            $rostersByOccurrence,
            $observations,
        ): array {
            $occurrenceId = (string) ($operation['occurrenceId'] ?? '');
            $participantRows = $participantsByOccurrence->get($occurrenceId, collect());
            $rosterRow = $rostersByOccurrence->get($occurrenceId);
            /** @var list<array<string,mixed>> $occurrenceParticipants */
            $occurrenceParticipants = array_values(
                $participantRows
                    ->filter(static fn (mixed $row): bool => is_array($row))
                    ->values()
                    ->all(),
            );

            return $this->occurrence(
                $operation,
                $occurrenceParticipants,
                is_array($rosterRow) ? $rosterRow : [],
                $observations,
            );
        }, $rallyOperations));
        $reasonCodes = [];
        $issueCount = 0;
        foreach ($projection as $occurrence) {
            foreach ($occurrence['issues'] as $issue) {
                $reasonCodes[] = (string) $issue['code'];
                $issueCount++;
            }
        }
        ReadModelTelemetry::record('rally_builder.rendered', $startedAt, [
            'event_id' => (string) $event->id,
            'actor_player_id' => $actorPlayerId,
            'alliance_id' => is_string($event->alliance_id) ? $event->alliance_id : null,
        ], [
            'occurrence_count' => count($projection),
            'player_count' => count($playerIds),
            'issue_count' => $issueCount,
        ], $reasonCodes);

        return $projection;
    }

    /**
     * @param  array<string,mixed>  $operation
     * @param  list<array<string,mixed>>  $participants
     * @param  array<string,mixed>  $rosterOperation
     * @param  array{available:bool,byPlayer:array<string,array{state:string,capturedAt:?string,source:?string}>}  $observations
     * @return array<string,mixed>
     */
    private function occurrence(
        array $operation,
        array $participants,
        array $rosterOperation,
        array $observations,
    ): array {
        $groups = array_values(array_filter($operation['groups'] ?? [], 'is_array'));
        $activeStatuses = ['assigned', 'confirmed', 'participated', 'absent'];
        $activeAssignments = [];
        $declinedAssignments = [];
        $groupsWithoutLead = [];
        $groupsWithJoinerGaps = [];
        $hasStandby = false;

        foreach ($groups as $group) {
            $activeInGroup = [];
            $activeLeads = 0;
            $activeJoiners = 0;
            foreach (array_values(array_filter($group['assignments'] ?? [], 'is_array')) as $assignment) {
                $status = (string) ($assignment['status'] ?? '');
                if ($status === 'declined') {
                    $declinedAssignments[] = $assignment;
                }
                if (! in_array($status, $activeStatuses, true)) {
                    continue;
                }
                $activeInGroup[] = $assignment;
                $activeAssignments[] = $assignment + ['groupId' => (string) ($group['id'] ?? '')];
                $role = (string) ($assignment['role'] ?? '');
                $activeLeads += $role === 'lead' ? 1 : 0;
                $activeJoiners += $role === 'joiner' ? 1 : 0;
                $hasStandby = $hasStandby || $role === 'standby';
            }

            if ($activeLeads === 0) {
                $groupsWithoutLead[] = (string) ($group['id'] ?? '');
            }
            $capacity = $group['maxJoiners'] ?? null;
            if (is_int($capacity) && $capacity > $activeJoiners) {
                $groupsWithJoinerGaps[] = [
                    'groupId' => (string) ($group['id'] ?? ''),
                    'count' => $capacity - $activeJoiners,
                ];
            }
        }

        $assignmentCounts = [];
        foreach ($activeAssignments as $assignment) {
            $playerId = (string) ($assignment['playerId'] ?? '');
            if ($playerId !== '') {
                $assignmentCounts[$playerId] = ($assignmentCounts[$playerId] ?? 0) + 1;
            }
        }

        $expectedPlayerIds = [];
        foreach ($participants as $participant) {
            if (($participant['registration'] ?? null) === 'registered') {
                $expectedPlayerIds[] = (string) ($participant['playerId'] ?? '');
            }
        }
        foreach (array_values(array_filter($rosterOperation['rosters'] ?? [], 'is_array')) as $roster) {
            foreach (array_values(array_filter($roster['members'] ?? [], 'is_array')) as $member) {
                if (! in_array((string) ($member['status'] ?? ''), ['declined', 'removed'], true)) {
                    $expectedPlayerIds[] = (string) ($member['playerId'] ?? '');
                }
            }
        }
        $expectedPlayerIds = array_values(array_unique(array_filter($expectedPlayerIds)));
        $unassigned = array_values(array_filter(
            $expectedPlayerIds,
            static fn (string $playerId): bool => ! isset($assignmentCounts[$playerId]),
        ));
        $duplicates = array_keys(array_filter(
            $assignmentCounts,
            static fn (int $count): bool => $count > 1,
        ));

        $assignedPlayerIds = array_keys($assignmentCounts);
        $stale = [];
        $unknown = [];
        if ($observations['available']) {
            foreach ($assignedPlayerIds as $playerId) {
                $state = $observations['byPlayer'][$playerId]['state'] ?? 'unknown';
                if ($state === 'stale') {
                    $stale[] = $playerId;
                } elseif ($state === 'unknown') {
                    $unknown[] = $playerId;
                }
            }
        }

        $issues = [
            $this->issue('registered_or_rostered_unassigned', 'blocking', count($unassigned), $unassigned),
            $this->issue('assigned_to_multiple_groups', 'blocking', count($duplicates), $duplicates),
            $this->issue('group_missing_lead', 'blocking', count($groupsWithoutLead), groupIds: $groupsWithoutLead),
            $this->issue('assignment_declined', 'blocking', count($declinedAssignments), array_values(array_unique(array_filter(array_map(
                static fn (array $assignment): string => (string) ($assignment['playerId'] ?? ''),
                $declinedAssignments,
            ))))),
            $this->issue(
                'joiner_capacity_gap',
                'warning',
                array_sum(array_column($groupsWithJoinerGaps, 'count')),
                groupIds: array_column($groupsWithJoinerGaps, 'groupId'),
            ),
            $this->issue('standby_not_assigned', 'warning', $groups !== [] && ! $hasStandby ? 1 : 0),
            $this->issue('governor_observation_stale', 'warning', count($stale), $stale),
            $this->issue('governor_observation_unknown', 'warning', count($unknown), $unknown),
        ];
        $issues = array_values(array_filter($issues, static fn (array $issue): bool => $issue['count'] > 0));
        $blocking = array_sum(array_map(
            static fn (array $issue): int => $issue['severity'] === 'blocking' ? (int) $issue['count'] : 0,
            $issues,
        ));

        return [
            'occurrenceId' => (string) ($operation['occurrenceId'] ?? ''),
            'startsAt' => $operation['startsAt'] ?? null,
            'state' => $groups === [] ? 'empty' : ($blocking > 0 ? 'needs_attention' : 'ready'),
            'groupCount' => count($groups),
            'assignmentCount' => count($activeAssignments),
            'leadCount' => count(array_filter($activeAssignments, static fn (array $assignment): bool => ($assignment['role'] ?? null) === 'lead')),
            'joinerCount' => count(array_filter($activeAssignments, static fn (array $assignment): bool => ($assignment['role'] ?? null) === 'joiner')),
            'standbyCount' => count(array_filter($activeAssignments, static fn (array $assignment): bool => ($assignment['role'] ?? null) === 'standby')),
            'blockingCount' => $blocking,
            'warningCount' => array_sum(array_map(
                static fn (array $issue): int => $issue['severity'] === 'warning' ? (int) $issue['count'] : 0,
                $issues,
            )),
            'observationState' => $observations['available'] ? 'available' : 'unavailable',
            'issues' => $issues,
        ];
    }

    /**
     * @param  list<string>  $playerIds
     * @param  list<string>  $groupIds
     * @return array{code:string,severity:string,count:int,playerIds:list<string>,groupIds:list<string>}
     */
    private function issue(
        string $code,
        string $severity,
        int $count,
        array $playerIds = [],
        array $groupIds = [],
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'count' => $count,
            'playerIds' => array_values(array_unique($playerIds)),
            'groupIds' => array_values(array_unique($groupIds)),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $rallies
     * @param  list<array<string,mixed>>  $participants
     * @param  list<array<string,mixed>>  $rosters
     * @return list<string>
     */
    private function playerIds(array $rallies, array $participants, array $rosters): array
    {
        $ids = [];
        foreach ($rallies as $operation) {
            foreach (array_values(array_filter($operation['groups'] ?? [], 'is_array')) as $group) {
                foreach (array_values(array_filter($group['assignments'] ?? [], 'is_array')) as $assignment) {
                    $ids[] = (string) ($assignment['playerId'] ?? '');
                }
            }
        }
        foreach ($participants as $participant) {
            $ids[] = (string) ($participant['playerId'] ?? '');
        }
        foreach ($rosters as $operation) {
            foreach (array_values(array_filter($operation['rosters'] ?? [], 'is_array')) as $roster) {
                foreach (array_values(array_filter($roster['members'] ?? [], 'is_array')) as $member) {
                    $ids[] = (string) ($member['playerId'] ?? '');
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<string>  $playerIds
     * @return array{available:bool,byPlayer:array<string,array{state:string,capturedAt:?string,source:?string}>}
     */
    private function observationStates(string $actorPlayerId, Event $event, array $playerIds): array
    {
        if ($event->scopeEnum() !== EventScope::Alliance
            || ! is_string($event->alliance_id)
            || ! $this->intelligenceAuthorization->allows(
                $actorPlayerId,
                $event->alliance_id,
                IntelligencePermission::View,
            )) {
            return ['available' => false, 'byPlayer' => []];
        }

        $entries = AllianceRosterEntry::query()
            ->where('alliance_id', $event->alliance_id)
            ->whereIn('player_id', $playerIds)
            ->get();
        $latest = $this->snapshots->latestForEntries($event->alliance_id, $entries);
        $staleBefore = CarbonImmutable::now('UTC')->subDays(PlayerSnapshotQuery::STALE_AFTER_DAYS);
        $byPlayer = [];

        foreach ($entries as $entry) {
            $snapshot = $latest[(string) $entry->id] ?? null;
            $byPlayer[(string) $entry->player_id] = [
                'state' => $snapshot === null
                    ? 'unknown'
                    : ($snapshot->captured_at->lessThan($staleBefore) ? 'stale' : 'current'),
                'capturedAt' => $snapshot?->captured_at->toIso8601String(),
                'source' => $snapshot?->source,
            ];
        }
        foreach ($playerIds as $playerId) {
            $byPlayer[$playerId] ??= ['state' => 'unknown', 'capturedAt' => null, 'source' => null];
        }

        return ['available' => true, 'byPlayer' => $byPlayer];
    }
}
