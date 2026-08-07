<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\CreateEventFromTemplate;
use App\Domain\Rallies\Actions\CreateEventRecommendedFormation;
use App\Domain\Notifications\Actions\CreateEventReminderRule;
use App\Domain\Events\Actions\CreateEventTemplate;
use App\Domain\Events\Actions\RecordEventAttendance;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Queries\AllianceEventQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Rallies\Actions\AssignRallyMember;
use App\Domain\Rallies\Actions\CreateRallyGroup;
use App\Domain\Rallies\Actions\CreateRallyGuidanceRule;
use App\Domain\Rallies\Actions\RecordRallyParticipation;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\EventRecommendedFormation;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\Models\RallyGroup;
use App\Domain\Rallies\Models\RallyGuidanceRule;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class EventManagementController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceEventQuery $eventQuery,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance();

        if (! $authorization->allows($user, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException;
        }

        $occurrences = $eventQuery->calendar($alliance, pastDays: 14, futureDays: 120);
        $occurrenceIds = $occurrences->pluck('id')->all();
        $eventIds = $occurrences->pluck('event_id')->unique()->values()->all();

        $templates = EventTemplate::query()
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $memberships = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();
        $guidance = RallyGuidanceRule::query()
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->orderBy('name')
            ->get();
        $recommendations = EventRecommendedFormation::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $groups = RallyGroup::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $groupIds = $groups->pluck('id')->all();
        $registrations = EventRegistration::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->where('status', '!=', EventRegistrationStatus::Cancelled->value)
            ->with('membership.user:id,name')
            ->orderBy('registered_at')
            ->get();
        $assignments = RallyAssignment::query()
            ->where('alliance_id', $alliance->id)
            ->whereIn('rally_group_id', $groupIds)
            ->with('membership.user:id,name')
            ->orderBy('rally_group_id')
            ->orderBy('slot_number')
            ->get();

        /** @var list<array<string, mixed>> $occurrenceData */
        $occurrenceData = [];
        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            if (! $event instanceof Event) {
                throw new LogicException('An event occurrence must reference an event.');
            }

            $occurrenceData[] = [
                'id' => (string) $occurrence->id,
                'eventId' => (string) $event->id,
                'title' => (string) $event->title,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'endsAt' => $occurrence->ends_at->toIso8601String(),
                'status' => $occurrence->status->value,
                'capacity' => $occurrence->capacity === null ? null : (int) $occurrence->capacity,
            ];
        }

        /** @var list<array<string, mixed>> $templateData */
        $templateData = [];
        foreach ($templates as $template) {
            $templateData[] = [
                'id' => (string) $template->id,
                'name' => (string) $template->name,
                'durationMinutes' => (int) $template->duration_minutes,
                'capacity' => $template->capacity === null ? null : (int) $template->capacity,
                'registrationOpensMinutesBefore' => $template->registration_opens_minutes_before === null
                    ? null
                    : (int) $template->registration_opens_minutes_before,
                'registrationClosesMinutesBefore' => (int) $template->registration_closes_minutes_before,
                'recurrenceFrequency' => $template->recurrence_frequency->value,
                'recurrenceInterval' => (int) $template->recurrence_interval,
                'instructions' => $template->instructions,
            ];
        }

        /** @var list<array{id: string, name: string}> $memberData */
        $memberData = [];
        foreach ($memberships as $membership) {
            $member = $membership->user;
            if (! $member instanceof User) {
                throw new LogicException('An alliance membership must reference a user.');
            }

            $memberData[] = [
                'id' => (string) $membership->id,
                'name' => (string) $member->name,
            ];
        }

        /** @var list<array<string, mixed>> $guidanceData */
        $guidanceData = [];
        foreach ($guidance as $rule) {
            $guidanceData[] = [
                'id' => (string) $rule->id,
                'name' => (string) $rule->name,
                'infantryPercent' => (int) $rule->infantry_percent,
                'cavalryPercent' => (int) $rule->cavalry_percent,
                'archerPercent' => (int) $rule->archer_percent,
                'heroRecommendations' => $rule->hero_recommendations ?? [],
                'leadRequirements' => $rule->lead_requirements,
                'joinerGuidance' => $rule->joiner_guidance,
                'source' => $rule->source,
                'rationale' => $rule->rationale,
                'effectiveFrom' => $rule->effective_from?->toDateString(),
                'effectiveUntil' => $rule->effective_until?->toDateString(),
            ];
        }

        /** @var list<array<string, mixed>> $recommendationData */
        $recommendationData = [];
        foreach ($recommendations as $formation) {
            $recommendationData[] = [
                'id' => (string) $formation->id,
                'occurrenceId' => (string) $formation->occurrence_id,
                'name' => (string) $formation->name,
                'assignmentRole' => (string) $formation->assignment_role,
            ];
        }

        /** @var list<array<string, mixed>> $groupData */
        $groupData = [];
        foreach ($groups as $group) {
            $groupData[] = [
                'id' => (string) $group->id,
                'occurrenceId' => (string) $group->occurrence_id,
                'name' => (string) $group->name,
                'maxJoiners' => $group->max_joiners === null ? null : (int) $group->max_joiners,
            ];
        }

        /** @var list<array<string, mixed>> $registrationData */
        $registrationData = [];
        foreach ($registrations as $registration) {
            $membership = $registration->membership;
            $member = $membership instanceof AllianceMembership ? $membership->user : null;
            $registrationData[] = [
                'id' => (string) $registration->id,
                'occurrenceId' => (string) $registration->occurrence_id,
                'membershipId' => (string) $registration->membership_id,
                'memberName' => $member instanceof User ? (string) $member->name : 'Unknown member',
                'status' => $registration->status->value,
            ];
        }

        /** @var list<array<string, mixed>> $assignmentData */
        $assignmentData = [];
        foreach ($assignments as $assignment) {
            $membership = $assignment->membership;
            $member = $membership instanceof AllianceMembership ? $membership->user : null;
            $assignmentData[] = [
                'id' => (string) $assignment->id,
                'groupId' => (string) $assignment->rally_group_id,
                'membershipId' => (string) $assignment->membership_id,
                'memberName' => $member instanceof User ? (string) $member->name : 'Unknown member',
                'role' => $assignment->role->value,
                'slotNumber' => $assignment->slot_number === null ? null : (int) $assignment->slot_number,
                'status' => $assignment->status->value,
            ];
        }

        return Inertia::render('Alliance/Events/Manage', [
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'timezone' => $alliance->timezone,
            ],
            'recurrenceOptions' => array_map(static fn (RecurrenceFrequency $frequency): string => $frequency->value, RecurrenceFrequency::cases()),
            'roleOptions' => array_map(static fn (RallyAssignmentRole $role): string => $role->value, RallyAssignmentRole::cases()),
            'templates' => $templateData,
            'occurrences' => $occurrenceData,
            'members' => $memberData,
            'guidance' => $guidanceData,
            'recommendations' => $recommendationData,
            'groups' => $groupData,
            'registrations' => $registrationData,
            'assignments' => $assignmentData,
            'eventIds' => $eventIds,
        ]);
    }

    public function storeEvent(
        Request $request,
        AllianceContext $context,
        CreateEvent $createEvent,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'first_local_start' => ['required', 'date_format:Y-m-d\\TH:i'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_opens_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'registration_closes_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'recurrence_frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['nullable', 'integer', 'between:1,52'],
            'recurrence_until_local' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);

        $createEvent->handle(
            actor: $this->user($request),
            alliance: $alliance,
            title: (string) $validated['title'],
            firstLocalStart: CarbonImmutable::createFromFormat(
                'Y-m-d\\TH:i',
                (string) $validated['first_local_start'],
                $alliance->timezone,
            ),
            durationMinutes: (int) $validated['duration_minutes'],
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            registrationOpensMinutesBefore: isset($validated['registration_opens_minutes_before'])
                ? (int) $validated['registration_opens_minutes_before']
                : null,
            registrationClosesMinutesBefore: (int) ($validated['registration_closes_minutes_before'] ?? 0),
            frequency: RecurrenceFrequency::from((string) $validated['recurrence_frequency']),
            recurrenceInterval: (int) ($validated['recurrence_interval'] ?? 1),
            recurrenceUntilLocal: isset($validated['recurrence_until_local'])
                ? CarbonImmutable::createFromFormat(
                    'Y-m-d\\TH:i',
                    (string) $validated['recurrence_until_local'],
                    $alliance->timezone,
                )
                : null,
            instructions: isset($validated['instructions']) ? (string) $validated['instructions'] : null,
        );

        return back()->with('status', 'event-created');
    }

    public function storeTemplate(
        Request $request,
        AllianceContext $context,
        CreateEventTemplate $createTemplate,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_opens_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'registration_closes_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'recurrence_frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['nullable', 'integer', 'between:1,52'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);

        $createTemplate->handle(
            actor: $this->user($request),
            alliance: $context->alliance(),
            name: (string) $validated['name'],
            durationMinutes: (int) $validated['duration_minutes'],
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            registrationOpensMinutesBefore: isset($validated['registration_opens_minutes_before'])
                ? (int) $validated['registration_opens_minutes_before']
                : null,
            registrationClosesMinutesBefore: (int) ($validated['registration_closes_minutes_before'] ?? 0),
            frequency: RecurrenceFrequency::from((string) $validated['recurrence_frequency']),
            recurrenceInterval: (int) ($validated['recurrence_interval'] ?? 1),
            instructions: isset($validated['instructions']) ? (string) $validated['instructions'] : null,
        );

        return back()->with('status', 'event-template-created');
    }

    public function storeTemplateEvent(
        Request $request,
        AllianceContext $context,
        CreateEventFromTemplate $createEvent,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $validated = $request->validate([
            'template_id' => ['required', 'string', 'ulid'],
            'title' => ['nullable', 'string', 'max:160'],
            'first_local_start' => ['required', 'date_format:Y-m-d\\TH:i'],
            'recurrence_until_local' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);
        $template = EventTemplate::query()
            ->whereKey((string) $validated['template_id'])
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->firstOrFail();

        $createEvent->handle(
            actor: $this->user($request),
            alliance: $alliance,
            template: $template,
            firstLocalStart: CarbonImmutable::createFromFormat(
                'Y-m-d\\TH:i',
                (string) $validated['first_local_start'],
                $alliance->timezone,
            ),
            recurrenceUntilLocal: isset($validated['recurrence_until_local'])
                ? CarbonImmutable::createFromFormat(
                    'Y-m-d\\TH:i',
                    (string) $validated['recurrence_until_local'],
                    $alliance->timezone,
                )
                : null,
            title: isset($validated['title']) ? (string) $validated['title'] : null,
        );

        return back()->with('status', 'event-created-from-template');
    }

    public function storeReminder(
        Request $request,
        AllianceContext $context,
        CreateEventReminderRule $createReminder,
        string $event,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $eventModel = Event::query()->whereKey($event)->where('alliance_id', $alliance->id)->firstOrFail();
        $validated = $request->validate([
            'minutes_before_start' => ['required', 'integer', 'between:1,10080'],
        ]);

        $createReminder->handle(
            $this->user($request),
            $alliance,
            $eventModel,
            (int) $validated['minutes_before_start'],
        );

        return back()->with('status', 'event-reminder-created');
    }

    public function storeGuidance(
        Request $request,
        AllianceContext $context,
        CreateRallyGuidanceRule $createGuidance,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'infantry_percent' => ['required', 'integer', 'between:0,100'],
            'cavalry_percent' => ['required', 'integer', 'between:0,100'],
            'archer_percent' => ['required', 'integer', 'between:0,100'],
            'hero_recommendations' => ['nullable', 'array', 'max:10'],
            'hero_recommendations.*' => ['string', 'max:100'],
            'lead_requirements' => ['nullable', 'string', 'max:5000'],
            'joiner_guidance' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', 'string', 'max:255'],
            'rationale' => ['nullable', 'string', 'max:5000'],
        ]);

        $createGuidance->handle(
            actor: $this->user($request),
            alliance: $context->alliance(),
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            effectiveFrom: CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['effective_from'], 'UTC'),
            effectiveUntil: isset($validated['effective_until'])
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['effective_until'], 'UTC')
                : null,
            heroRecommendations: $this->strings($validated['hero_recommendations'] ?? []),
            leadRequirements: isset($validated['lead_requirements']) ? (string) $validated['lead_requirements'] : null,
            joinerGuidance: isset($validated['joiner_guidance']) ? (string) $validated['joiner_guidance'] : null,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            source: isset($validated['source']) ? (string) $validated['source'] : null,
            rationale: isset($validated['rationale']) ? (string) $validated['rationale'] : null,
        );

        return back()->with('status', 'rally-guidance-created');
    }

    public function storeRecommendedFormation(
        Request $request,
        AllianceContext $context,
        CreateEventRecommendedFormation $createFormation,
        string $occurrence,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $occurrenceModel = EventOccurrence::query()
            ->whereKey($occurrence)
            ->where('alliance_id', $alliance->id)
            ->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'assignment_role' => ['required', Rule::enum(RallyAssignmentRole::class)],
            'guidance_rule_id' => ['nullable', 'string', 'ulid'],
            'heroes' => ['nullable', 'array', 'max:5'],
            'heroes.*' => ['string', 'max:100'],
            'infantry_percent' => ['required', 'integer', 'between:0,100'],
            'cavalry_percent' => ['required', 'integer', 'between:0,100'],
            'archer_percent' => ['required', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $guidance = isset($validated['guidance_rule_id'])
            ? RallyGuidanceRule::query()
                ->whereKey((string) $validated['guidance_rule_id'])
                ->where('alliance_id', $alliance->id)
                ->firstOrFail()
            : null;

        $createFormation->handle(
            actor: $this->user($request),
            alliance: $alliance,
            occurrence: $occurrenceModel,
            name: (string) $validated['name'],
            assignmentRole: RallyAssignmentRole::from((string) $validated['assignment_role']),
            composition: $this->composition($validated),
            heroes: $this->strings($validated['heroes'] ?? []),
            guidanceRule: $guidance,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'event-formation-created');
    }

    public function storeRallyGroup(
        Request $request,
        AllianceContext $context,
        CreateRallyGroup $createGroup,
        string $occurrence,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $occurrenceModel = EventOccurrence::query()
            ->whereKey($occurrence)
            ->where('alliance_id', $alliance->id)
            ->firstOrFail();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_joiners' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'recommended_formation_id' => ['nullable', 'string', 'ulid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $formation = isset($validated['recommended_formation_id'])
            ? EventRecommendedFormation::query()
                ->whereKey((string) $validated['recommended_formation_id'])
                ->where('alliance_id', $alliance->id)
                ->where('occurrence_id', $occurrenceModel->id)
                ->firstOrFail()
            : null;

        $createGroup->handle(
            actor: $this->user($request),
            alliance: $alliance,
            occurrence: $occurrenceModel,
            name: (string) $validated['name'],
            maxJoiners: isset($validated['max_joiners']) ? (int) $validated['max_joiners'] : null,
            recommendedFormation: $formation,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'rally-group-created');
    }

    public function assignMember(
        Request $request,
        AllianceContext $context,
        AssignRallyMember $assignMember,
        string $group,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $groupModel = RallyGroup::query()->whereKey($group)->where('alliance_id', $alliance->id)->firstOrFail();
        $validated = $request->validate([
            'membership_id' => ['required', 'string', 'ulid'],
            'role' => ['required', Rule::enum(RallyAssignmentRole::class)],
            'slot_number' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $membership = AllianceMembership::query()
            ->whereKey((string) $validated['membership_id'])
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->firstOrFail();

        $assignMember->handle(
            actor: $this->user($request),
            alliance: $alliance,
            group: $groupModel,
            membership: $membership,
            role: RallyAssignmentRole::from((string) $validated['role']),
            slotNumber: isset($validated['slot_number']) ? (int) $validated['slot_number'] : null,
        );

        return back()->with('status', 'rally-assignment-saved');
    }

    public function recordAttendance(
        Request $request,
        AllianceContext $context,
        RecordEventAttendance $recordAttendance,
        string $occurrence,
        string $registration,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $occurrenceModel = EventOccurrence::query()
            ->whereKey($occurrence)
            ->where('alliance_id', $alliance->id)
            ->firstOrFail();
        $registrationModel = EventRegistration::query()
            ->whereKey($registration)
            ->where('alliance_id', $alliance->id)
            ->where('occurrence_id', $occurrenceModel->id)
            ->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EventRegistrationStatus::Attended->value,
                EventRegistrationStatus::NoShow->value,
            ])],
        ]);

        $recordAttendance->handle(
            $this->user($request),
            $alliance,
            $occurrenceModel,
            $registrationModel,
            EventRegistrationStatus::from((string) $validated['status']),
        );

        return back()->with('status', 'event-attendance-recorded');
    }

    public function recordParticipation(
        Request $request,
        AllianceContext $context,
        RecordRallyParticipation $recordParticipation,
        string $assignment,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $assignmentModel = RallyAssignment::query()
            ->whereKey($assignment)
            ->where('alliance_id', $alliance->id)
            ->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                RallyAssignmentStatus::Participated->value,
                RallyAssignmentStatus::NoShow->value,
            ])],
        ]);

        $recordParticipation->handle(
            $this->user($request),
            $alliance,
            $assignmentModel,
            RallyAssignmentStatus::from((string) $validated['status']),
        );

        return back()->with('status', 'rally-participation-recorded');
    }

    /** @param array<string, mixed> $data */
    private function composition(array $data): FormationComposition
    {
        return new FormationComposition(
            (int) $data['infantry_percent'],
            (int) $data['cavalry_percent'],
            (int) $data['archer_percent'],
        );
    }

    /** @return list<string> */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value)));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
