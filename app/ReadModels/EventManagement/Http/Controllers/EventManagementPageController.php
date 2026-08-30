<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\BattlePlans\Queries\EventObjectiveQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Events\Services\EventTypeProfileResolver;
use App\Contexts\Operations\Participation\Queries\EventParticipationQuery;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Polls\Queries\EventPhasePollQuery;
use App\Contexts\Operations\Rallies\Queries\EventRallyQuery;
use App\Contexts\Operations\Results\Queries\EventResultQuery;
use App\Contexts\Operations\Rosters\Queries\EventRosterQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\EventTerritoryPlanningQuery;
use App\ReadModels\EventAnalysis\Queries\EventPlayerIntelligenceQuery;
use App\ReadModels\EventManagement\Queries\EventCommandQuery;
use App\ReadModels\EventManagement\Queries\RallyRosterBuilderQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EventManagementPageController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function __invoke(
        Request $request,
        string $event,
        EventCalendarQuery $query,
        EventParticipationQuery $participation,
        EventPhasePollQuery $phasePolls,
        EventObjectiveQuery $objectives,
        EventResultQuery $results,
        EventPlayerIntelligenceQuery $intelligence,
        EventRosterQuery $rosters,
        EventRallyQuery $rallies,
        EventTypeProfileResolver $profiles,
        EventTerritoryPlanningQuery $territoryPlanning,
        EventCommandQuery $eventCommand,
        RallyRosterBuilderQuery $rallyRosterBuilder,
    ): Response {
        $user = $this->user($request);
        $actor = $this->player();
        $record = $query->eventForManage($actor, $event);
        $profile = $profiles->resolve($record->eventType);
        $workflowDimensions = $profile['profile_enabled'] === true
            ? $profile['workflow_dimensions']
            : [];
        $reminderAudiences = [EventReminderAudience::AllScopePlayers->value];

        if ($record->scope === EventScope::Player) {
            $reminderAudiences[] = EventReminderAudience::Target->value;
        }
        if (in_array(EventWorkflowDimension::Participation->value, $workflowDimensions, true)) {
            $reminderAudiences[] = EventReminderAudience::Responded->value;
            $reminderAudiences[] = EventReminderAudience::Registered->value;
        }
        if (in_array(EventWorkflowDimension::Roster->value, $workflowDimensions, true)) {
            $reminderAudiences[] = EventReminderAudience::Rostered->value;
        }
        $participantOperations = $this->supports($workflowDimensions, EventWorkflowDimension::Participation)
            ? $participation->management($record)
            : [];
        $rosterOperations = $this->supports($workflowDimensions, EventWorkflowDimension::Roster)
            ? $rosters->management($record)
            : [];
        $rallyOperations = $this->supports($workflowDimensions, EventWorkflowDimension::Rallies)
            ? $rallies->management($record)
            : [];
        $rallyBuilder = $this->supportsAll($workflowDimensions, [
            EventWorkflowDimension::Participation,
            EventWorkflowDimension::Roster,
            EventWorkflowDimension::Rallies,
        ])
            ? $rallyRosterBuilder->forEvent(
                $actor->playerId,
                $record,
                $rallyOperations,
                $participantOperations,
                $rosterOperations,
            )
            : [];

        return Inertia::render('Operations/Events/Manage', [
            'user' => $this->identity($user),
            'event' => $this->managementPayload($record, $profile),
            'eventCommand' => $eventCommand->forEvent(
                $actor,
                $record,
                $request->string('occurrence')->toString(),
            ),
            'participants' => $participantOperations,
            'operations' => $phasePolls->management($record),
            'battlePlan' => $this->supports($workflowDimensions, EventWorkflowDimension::BattleAssignments)
                ? $objectives->management($record)
                : [],
            'resultOperations' => $this->supports($workflowDimensions, EventWorkflowDimension::Results)
                ? $results->management($record)
                : [],
            'playerIntelligence' => $this->supports($workflowDimensions, EventWorkflowDimension::Debrief)
                ? $intelligence->forEvent($record)
                : [],
            'rosterOperations' => $rosterOperations,
            'rallyOperations' => $rallyOperations,
            'rallyBuilder' => $rallyBuilder,
            'territoryPlanning' => $this->supports($workflowDimensions, EventWorkflowDimension::TerritoryPlan)
                ? $territoryPlanning->management($actor->playerId, $record)
                : ['supported' => false, 'availableRevisions' => [], 'attachments' => []],
            'reminderAudiences' => array_values(array_unique($reminderAudiences)),
            'reminderRules' => EventReminderRule::query()
                ->where('event_id', $record->id)
                ->orderBy('minutes_before')
                ->get()
                ->map(static fn (EventReminderRule $rule): array => [
                    'id' => (string) $rule->id,
                    'pollId' => $rule->poll_id === null ? null : (string) $rule->poll_id,
                    'trigger' => $rule->trigger_type->value,
                    'minutesBefore' => (int) $rule->minutes_before,
                    'audience' => $rule->audience->value,
                    'channel' => (string) $rule->channel,
                    'enabled' => (bool) $rule->is_enabled,
                ])
                ->all(),
        ]);
    }

    /**
     * @param  array<string,mixed>  $profile
     * @return array<string,mixed>
     */
    private function managementPayload(Event $event, array $profile): array
    {
        return [
            'id' => (string) $event->id,
            'eventTypeId' => (string) $event->event_type_id,
            'targetId' => match ($event->scope) {
                EventScope::Player => (string) $event->player_id,
                EventScope::Alliance => (string) $event->alliance_id,
                EventScope::Kingdom => (string) $event->kingdom_id,
            },
            'nameKey' => (string) $event->eventType->name_key,
            'title' => $event->title,
            'scope' => $event->scope->value,
            'timezone' => (string) $event->timezone,
            'firstLocalStart' => $event->starts_at
                ->setTimezone($event->timezone)
                ->format('Y-m-d\TH:i'),
            'instructions' => $event->instructions,
            'durationMinutes' => $event->duration_minutes,
            'capacity' => $event->capacity,
            'registrationOpensMinutesBefore' => $event->registration_opens_minutes_before,
            'registrationClosesMinutesBefore' => $event->registration_closes_minutes_before,
            'recurrencePolicy' => $event->recurrence_policy->value,
            'recurrenceFrequency' => $event->recurrence_frequency->value,
            'recurrenceInterval' => $event->recurrence_interval,
            'recurrenceUntilLocal' => $event->recurrence_until
                ?->setTimezone($event->timezone)
                ->format('Y-m-d\TH:i'),
            'settings' => $event->settings ?? [],
            'profile' => $profile,
            'workflowDimensions' => $profile['profile_enabled'] === true
                ? $profile['workflow_dimensions']
                : [],
            'createdByPlayerId' => $event->created_by_player_id,
            'updatedByPlayerId' => $event->updated_by_player_id,
            'occurrences' => $event->occurrences
                ->sortBy('starts_at')
                ->map(static fn ($occurrence): array => [
                    'id' => (string) $occurrence->id,
                    'startsAt' => $occurrence->starts_at->toIso8601String(),
                    'endsAt' => $occurrence->ends_at->toIso8601String(),
                    'status' => $occurrence->status->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @param list<string> $dimensions */
    private function supports(array $dimensions, EventWorkflowDimension $dimension): bool
    {
        return in_array($dimension->value, $dimensions, true);
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<EventWorkflowDimension>  $required
     */
    private function supportsAll(array $dimensions, array $required): bool
    {
        foreach ($required as $dimension) {
            if (! $this->supports($dimensions, $dimension)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{name:string,email:string} */
    private function identity(User $user): array
    {
        return [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }

    private function player(): PlayerReference
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless(
            $player instanceof PlayerReference,
            409,
            'Select a Player before performing Event operations.',
        );

        return $player;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
