<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Queries;

use App\Contexts\Operations\BattlePlans\Queries\EventBattlePlanCommandQuery;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Queries\EventParticipationQuery;
use App\Contexts\Operations\Polls\Queries\EventPhasePollQuery;
use App\Contexts\Operations\Rallies\Queries\EventRallyCommandQuery;
use App\Contexts\Operations\Rosters\Queries\EventRosterQuery;
use App\ReadModels\EventManagement\Enums\EventCommandItemStatus as Status;
use App\ReadModels\EventManagement\Enums\EventCommandSeverity as Severity;
use App\ReadModels\EventManagement\Support\EventCommandItems as Items;
use App\ReadModels\EventManagement\Support\EventCommandOwnerReader;
use Carbon\CarbonImmutable;
use DateTimeZone;

final readonly class EventCommandOperationalReadinessQuery
{
    public function __construct(
        private EventParticipationQuery $participation,
        private EventPhasePollQuery $polls,
        private EventRosterQuery $rosters,
        private EventBattlePlanCommandQuery $battlePlans,
        private EventRallyCommandQuery $rallies,
        private EventCommandOwnerReader $owners,
    ) {}

    /**
     * @param  list<string>  $capabilities
     * @return list<array<string, mixed>>
     */
    public function forOccurrence(
        Event $event,
        EventOccurrence $occurrence,
        array $capabilities,
        CarbonImmutable $now,
    ): array {
        $sections = [$this->schedule($event, $occurrence)];

        if (
            $this->has($capabilities, EventCapability::Responses)
            || $this->has($capabilities, EventCapability::Registration)
            || $this->has($capabilities, EventCapability::Attendance)
        ) {
            $sections[] = $this->participation($event, $occurrence, $capabilities, $now);
        }
        if ($this->has($capabilities, EventCapability::Polls)) {
            $sections[] = $this->polls($event, $occurrence);
        }
        if ($this->has($capabilities, EventCapability::Rosters)) {
            $sections[] = $this->rosters($event, $occurrence);
        }
        if ($this->has($capabilities, EventCapability::Objectives)) {
            $sections[] = $this->battlePlan($event, $occurrence);
        }
        if ($this->rallyApplicable($capabilities)) {
            $sections[] = $this->rallies($event, $occurrence);
        }

        return $sections;
    }

    /** @return array<string, mixed> */
    private function schedule(Event $event, EventOccurrence $occurrence): array
    {
        $valid = in_array((string) $event->timezone, DateTimeZone::listIdentifiers(), true)
            && $occurrence->ends_at->greaterThan($occurrence->starts_at);

        return Items::section('schedule', 'events.command.sections.schedule', 'readiness', [
            Items::make(
                'schedule.valid',
                'readiness',
                $valid ? Status::Complete : Status::NeedsAttention,
                $valid ? Severity::Informational : Severity::Blocking,
                'operations.events',
                $valid
                    ? 'events.command.items.scheduleReady'
                    : 'events.command.items.scheduleInvalid',
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'schedule',
                    'events.command.actions.reviewSchedule',
                ),
            ),
        ]);
    }

    /**
     * @param  list<string>  $capabilities
     * @return array<string, mixed>
     */
    private function participation(
        Event $event,
        EventOccurrence $occurrence,
        array $capabilities,
        CarbonImmutable $now,
    ): array {
        $summary = $this->owners->read(
            'operations.participation',
            $event,
            $occurrence,
            fn (): array => $this->participation->commandSummary($occurrence),
        );
        if ($summary === null) {
            return Items::section('participation', 'events.command.sections.participation', 'readiness', [
                $this->unknown(
                    $event,
                    $occurrence,
                    'participation.unavailable',
                    'operations.participation',
                    'events.command.items.participationUnavailable',
                    'participation',
                ),
            ]);
        }

        $items = [];
        if ($this->has($capabilities, EventCapability::Responses)) {
            $unanswered = (int) $summary['unansweredCount'];
            $items[] = Items::make(
                'participation.unanswered',
                'readiness',
                $unanswered > 0 ? Status::NeedsAttention : Status::Complete,
                $unanswered > 0 ? Severity::Blocking : Severity::Informational,
                'operations.participation',
                $unanswered > 0
                    ? 'events.command.items.unansweredMembers'
                    : 'events.command.items.responsesComplete',
                [
                    'count' => $unanswered,
                    'responseCount' => (int) $summary['responseCount'],
                    'eligibleCount' => (int) $summary['eligibleCount'],
                ],
                $unanswered,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'participation',
                    'events.command.actions.reviewParticipation',
                ),
            );
        }
        if ($this->has($capabilities, EventCapability::Registration)) {
            $items[] = $this->registration($event, $occurrence, $summary, $now);
        }
        if ($this->has($capabilities, EventCapability::Waitlist)) {
            $waitlist = (int) $summary['waitlistCount'];
            $items[] = Items::make(
                'participation.waitlist',
                'readiness',
                $waitlist > 0 ? Status::Warning : Status::Complete,
                $waitlist > 0 ? Severity::Warning : Severity::Informational,
                'operations.participation',
                $waitlist > 0
                    ? 'events.command.items.waitlistActive'
                    : 'events.command.items.waitlistClear',
                ['count' => $waitlist],
                $waitlist,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'participation',
                    'events.command.actions.reviewParticipation',
                ),
            );
        }

        return Items::section('participation', 'events.command.sections.participation', 'readiness', $items);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function registration(
        Event $event,
        EventOccurrence $occurrence,
        array $summary,
        CarbonImmutable $now,
    ): array {
        $opens = $event->registration_opens_minutes_before;
        $closes = $event->registration_closes_minutes_before;
        $state = 'not_configured';
        if ($opens !== null || $closes !== null) {
            $openAt = $opens === null
                ? null
                : CarbonImmutable::instance($occurrence->starts_at)->subMinutes((int) $opens);
            $closeAt = $closes === null
                ? CarbonImmutable::instance($occurrence->starts_at)
                : CarbonImmutable::instance($occurrence->starts_at)->subMinutes((int) $closes);
            $state = match (true) {
                $openAt !== null && $now->lessThan($openAt) => 'not_open',
                $closeAt !== null && $now->greaterThanOrEqualTo($closeAt) => 'closed',
                default => 'open',
            };
        }

        return Items::make(
            'participation.registration',
            'readiness',
            Status::Complete,
            Severity::Informational,
            'operations.participation',
            'events.command.items.registrationState',
            [
                'state' => $state,
                'registeredCount' => (int) $summary['registeredCount'],
                'waitlistCount' => (int) $summary['waitlistCount'],
                'capacity' => $event->capacity,
            ],
            (int) $summary['registeredCount'],
            handoff: Items::handoff(
                $event,
                $occurrence,
                'participation',
                'events.command.actions.reviewParticipation',
            ),
        );
    }

    /** @return array<string, mixed> */
    private function polls(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read(
            'operations.polls',
            $event,
            $occurrence,
            fn (): array => $this->polls->commandSummary($occurrence),
        );
        if ($summary === null) {
            return Items::section('polls', 'events.command.sections.polls', 'readiness', [
                $this->unknown(
                    $event,
                    $occurrence,
                    'polls.unavailable',
                    'operations.polls',
                    'events.command.items.pollsUnavailable',
                    'polls',
                ),
            ]);
        }

        $unresolved = (int) $summary['unresolvedCount'];

        return Items::section('polls', 'events.command.sections.polls', 'readiness', [
            Items::make(
                'polls.unresolved',
                'readiness',
                $unresolved > 0 ? Status::NeedsAttention : Status::Complete,
                $unresolved > 0 ? Severity::Blocking : Severity::Informational,
                'operations.polls',
                $unresolved > 0
                    ? 'events.command.items.pollsUnresolved'
                    : 'events.command.items.pollsResolved',
                ['count' => $unresolved],
                $unresolved,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'polls',
                    'events.command.actions.reviewPolls',
                ),
            ),
        ]);
    }

    /** @return array<string, mixed> */
    private function rosters(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read(
            'operations.rosters',
            $event,
            $occurrence,
            fn (): array => $this->rosters->commandSummary($occurrence),
        );
        if ($summary === null) {
            return Items::section('rosters', 'events.command.sections.rosters', 'readiness', [
                $this->unknown(
                    $event,
                    $occurrence,
                    'roster.unavailable',
                    'operations.rosters',
                    'events.command.items.rosterUnavailable',
                    'rosters',
                ),
            ]);
        }

        $items = [];
        $rosterCount = (int) $summary['rosterCount'];
        if ($rosterCount === 0) {
            $items[] = Items::make(
                'roster.missing',
                'readiness',
                Status::NeedsAttention,
                Severity::Blocking,
                'operations.rosters',
                'events.command.items.rosterMissing',
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'rosters',
                    'events.command.actions.openRoster',
                ),
            );
        } else {
            $unfilled = (int) $summary['unfilledSlots'];
            $unassigned = (int) $summary['unassignedCount'];
            $items[] = Items::make(
                'roster.unfilled_slots',
                'readiness',
                $unfilled > 0 ? Status::NeedsAttention : Status::Complete,
                $unfilled > 0 ? Severity::Blocking : Severity::Informational,
                'operations.rosters',
                $unfilled > 0
                    ? 'events.command.items.rosterUnfilled'
                    : 'events.command.items.rosterFilled',
                ['count' => $unfilled],
                $unfilled,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'rosters',
                    'events.command.actions.openRoster',
                ),
            );
            $items[] = Items::make(
                'roster.unassigned',
                'readiness',
                $unassigned > 0 ? Status::NeedsAttention : Status::Complete,
                $unassigned > 0 ? Severity::Blocking : Severity::Informational,
                'operations.rosters',
                $unassigned > 0
                    ? 'events.command.items.rosterUnassigned'
                    : 'events.command.items.rosterAssigned',
                ['count' => $unassigned],
                $unassigned,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'rosters',
                    'events.command.actions.openRoster',
                ),
            );
        }

        $warnings = (int) $summary['warningCount'];
        if ($warnings > 0) {
            $items[] = Items::make(
                'roster.warnings',
                'readiness',
                Status::Warning,
                Severity::Warning,
                'operations.rosters',
                'events.command.items.rosterWarnings',
                ['count' => $warnings],
                $warnings,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'rosters',
                    'events.command.actions.openRoster',
                ),
            );
        }

        return Items::section('rosters', 'events.command.sections.rosters', 'readiness', $items);
    }

    /** @return array<string, mixed> */
    private function battlePlan(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read(
            'operations.battle_plans',
            $event,
            $occurrence,
            fn (): array => $this->battlePlans->forOccurrence($occurrence),
        );
        if ($summary === null) {
            return Items::section('battle-plan', 'events.command.sections.battlePlan', 'readiness', [
                $this->unknown(
                    $event,
                    $occurrence,
                    'battle_plan.unavailable',
                    'operations.battle_plans',
                    'events.command.items.battlePlanUnavailable',
                    'battle-plan',
                ),
            ]);
        }

        $objectives = (int) $summary['objectiveCount'];
        $unassigned = (int) $summary['unassignedPlayerCount'];
        $invalid = (int) $summary['invalidAssignmentCount'];
        $items = [
            Items::make(
                'battle_plan.objectives',
                'readiness',
                $objectives === 0 ? Status::NeedsAttention : Status::Complete,
                $objectives === 0 ? Severity::Blocking : Severity::Informational,
                'operations.battle_plans',
                $objectives === 0
                    ? 'events.command.items.objectivesMissing'
                    : 'events.command.items.objectivesConfigured',
                ['count' => $objectives],
                $objectives,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'battle-plan',
                    'events.command.actions.openBattlePlan',
                ),
            ),
            Items::make(
                'battle_plan.unassigned',
                'readiness',
                $unassigned > 0 ? Status::NeedsAttention : Status::Complete,
                $unassigned > 0 ? Severity::Blocking : Severity::Informational,
                'operations.battle_plans',
                $unassigned > 0
                    ? 'events.command.items.battlePlanUnassigned'
                    : 'events.command.items.battlePlanAssigned',
                ['count' => $unassigned],
                $unassigned,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'battle-plan',
                    'events.command.actions.openBattlePlan',
                ),
            ),
        ];
        if ($invalid > 0) {
            $items[] = Items::make(
                'battle_plan.invalid_assignments',
                'readiness',
                Status::NeedsAttention,
                Severity::Blocking,
                'operations.battle_plans',
                'events.command.items.battlePlanInvalidAssignments',
                ['count' => $invalid],
                $invalid,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'battle-plan',
                    'events.command.actions.openBattlePlan',
                ),
            );
        }

        return Items::section('battle-plan', 'events.command.sections.battlePlan', 'readiness', $items);
    }

    /** @return array<string, mixed> */
    private function rallies(Event $event, EventOccurrence $occurrence): array
    {
        $summary = $this->owners->read(
            'operations.rallies',
            $event,
            $occurrence,
            fn (): array => $this->rallies->forOccurrence($occurrence),
        );
        if ($summary === null) {
            return Items::section('rallies', 'events.command.sections.rallies', 'readiness', [
                $this->unknown(
                    $event,
                    $occurrence,
                    'rallies.unavailable',
                    'operations.rallies',
                    'events.command.items.ralliesUnavailable',
                    'rallies',
                ),
            ]);
        }

        $groups = (int) $summary['groupCount'];
        $withoutLead = (int) $summary['groupsWithoutLeadCount'];
        $items = [
            Items::make(
                'rallies.groups',
                'readiness',
                $groups === 0 ? Status::NeedsAttention : Status::Complete,
                $groups === 0 ? Severity::Blocking : Severity::Informational,
                'operations.rallies',
                $groups === 0
                    ? 'events.command.items.ralliesMissing'
                    : 'events.command.items.ralliesConfigured',
                ['count' => $groups],
                $groups,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'rallies',
                    'events.command.actions.openRallies',
                ),
            ),
        ];
        if ($groups > 0) {
            $items[] = Items::make(
                'rallies.leads',
                'readiness',
                $withoutLead > 0 ? Status::NeedsAttention : Status::Complete,
                $withoutLead > 0 ? Severity::Blocking : Severity::Informational,
                'operations.rallies',
                $withoutLead > 0
                    ? 'events.command.items.ralliesWithoutLead'
                    : 'events.command.items.rallyLeadsReady',
                ['count' => $withoutLead],
                $withoutLead,
                handoff: Items::handoff(
                    $event,
                    $occurrence,
                    'rallies',
                    'events.command.actions.openRallies',
                ),
            );
        }

        return Items::section('rallies', 'events.command.sections.rallies', 'readiness', $items);
    }

    /** @return array<string, mixed> */
    private function unknown(
        Event $event,
        EventOccurrence $occurrence,
        string $code,
        string $owner,
        string $messageKey,
        string $anchor,
    ): array {
        return Items::make(
            $code,
            'readiness',
            Status::Unknown,
            Severity::Blocking,
            $owner,
            $messageKey,
            handoff: Items::handoff(
                $event,
                $occurrence,
                $anchor,
                'events.command.actions.openOwnerWorkflow',
            ),
        );
    }

    /** @param  list<string>  $capabilities */
    private function has(array $capabilities, EventCapability $capability): bool
    {
        return in_array($capability->value, $capabilities, true);
    }

    /** @param  list<string>  $capabilities */
    private function rallyApplicable(array $capabilities): bool
    {
        return $this->has($capabilities, EventCapability::RallyGuidance)
            || $this->has($capabilities, EventCapability::Formations);
    }
}
