<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Contexts\Operations\EventCore\Queries\EventCalendarQuery;
use App\Contexts\Operations\Rallies\Actions\AssignRallyPlayer;
use App\Contexts\Operations\Rallies\Actions\RecordRallyParticipation;
use App\Contexts\Operations\Rallies\Actions\RemoveRallyPlayer;
use App\Contexts\Operations\Rallies\Actions\RespondRallyAssignment;
use App\Contexts\Operations\Rallies\Actions\SaveEventRecommendedFormation;
use App\Contexts\Operations\Rallies\Actions\SaveRallyGroup;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\EventRecommendedFormation;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Models\RallyGuidanceRule;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventRallyController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function storeFormation(Request $request, string $occurrence, EventCalendarQuery $events, SaveEventRecommendedFormation $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validateRecommendedFormation($request);
        $guidance = isset($validated['guidance_rule_id']) && $validated['guidance_rule_id'] !== null
            ? RallyGuidanceRule::query()->whereKey((string) $validated['guidance_rule_id'])->firstOrFail()
            : null;
        $save->handle(
            actor: $actor,
            occurrence: $record,
            allianceId: (string) $validated['alliance_id'],
            key: (string) $validated['key'],
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            heroes: $validated['heroes'] ?? [],
            assignmentRole: isset($validated['assignment_role']) && $validated['assignment_role'] !== null ? RallyAssignmentRole::from((string) $validated['assignment_role']) : null,
            guidance: $guidance,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'rally-formation-saved');
    }

    public function updateFormation(Request $request, string $occurrence, string $formation, EventCalendarQuery $events, SaveEventRecommendedFormation $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $formationRecord = EventRecommendedFormation::query()->whereKey($formation)->where('occurrence_id', $record->id)->firstOrFail();
        $validated = $this->validateRecommendedFormation($request);
        $guidance = isset($validated['guidance_rule_id']) && $validated['guidance_rule_id'] !== null
            ? RallyGuidanceRule::query()->whereKey((string) $validated['guidance_rule_id'])->firstOrFail()
            : null;
        $save->handle(
            actor: $actor,
            occurrence: $record,
            allianceId: (string) $validated['alliance_id'],
            key: (string) $validated['key'],
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            heroes: $validated['heroes'] ?? [],
            assignmentRole: isset($validated['assignment_role']) && $validated['assignment_role'] !== null ? RallyAssignmentRole::from((string) $validated['assignment_role']) : null,
            guidance: $guidance,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            formation: $formationRecord,
        );

        return back()->with('status', 'rally-formation-saved');
    }

    public function storeGroup(Request $request, string $occurrence, EventCalendarQuery $events, SaveRallyGroup $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validateGroup($request);
        $recommended = isset($validated['recommended_formation_id']) && $validated['recommended_formation_id'] !== null
            ? EventRecommendedFormation::query()->whereKey((string) $validated['recommended_formation_id'])->firstOrFail()
            : null;
        $save->handle(
            actor: $actor,
            occurrence: $record,
            allianceId: (string) $validated['alliance_id'],
            name: (string) $validated['name'],
            maxJoiners: isset($validated['max_joiners']) ? (int) $validated['max_joiners'] : null,
            recommendedFormation: $recommended,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'rally-group-saved');
    }

    public function updateGroup(Request $request, string $occurrence, string $group, EventCalendarQuery $events, SaveRallyGroup $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $groupRecord = RallyGroup::query()->whereKey($group)->where('occurrence_id', $record->id)->firstOrFail();
        $validated = $this->validateGroup($request);
        $recommended = isset($validated['recommended_formation_id']) && $validated['recommended_formation_id'] !== null
            ? EventRecommendedFormation::query()->whereKey((string) $validated['recommended_formation_id'])->firstOrFail()
            : null;
        $save->handle(
            actor: $actor,
            occurrence: $record,
            allianceId: (string) $validated['alliance_id'],
            name: (string) $validated['name'],
            maxJoiners: isset($validated['max_joiners']) ? (int) $validated['max_joiners'] : null,
            recommendedFormation: $recommended,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            group: $groupRecord,
        );

        return back()->with('status', 'rally-group-saved');
    }

    public function assign(Request $request, string $occurrence, string $group, string $player, EventCalendarQuery $events, AssignRallyPlayer $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $groupRecord = RallyGroup::query()->whereKey($group)->where('occurrence_id', $record->id)->firstOrFail();
        $playerRecord = Player::query()->whereKey($player)->firstOrFail();
        $validated = $request->validate([
            'role' => ['required', Rule::enum(RallyAssignmentRole::class)],
            'slot_number' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $assign->handle(
            actor: $actor,
            group: $groupRecord,
            player: $playerRecord,
            role: RallyAssignmentRole::from((string) $validated['role']),
            slotNumber: isset($validated['slot_number']) ? (int) $validated['slot_number'] : null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'rally-player-assigned');
    }

    public function remove(Request $request, string $occurrence, string $group, string $player, EventCalendarQuery $events, RemoveRallyPlayer $remove): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $assignment = RallyAssignment::query()
            ->where('player_id', $player)
            ->whereHas('rallyGroup', static fn ($query) => $query->where('id', $group)->where('occurrence_id', $record->id))
            ->firstOrFail();
        $remove->handle($actor, $assignment);

        return back()->with('status', 'rally-player-removed');
    }

    public function respond(Request $request, string $occurrence, string $assignment, EventCalendarQuery $events, RespondRallyAssignment $respond): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $assignmentRecord = RallyAssignment::query()
            ->whereKey($assignment)
            ->whereHas('rallyGroup', static fn ($query) => $query->where('occurrence_id', $record->id))
            ->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in([RallyAssignmentStatus::Confirmed->value, RallyAssignmentStatus::Declined->value])],
        ]);
        $respond->handle($actor, $assignmentRecord, $actor, RallyAssignmentStatus::from((string) $validated['status']));

        return back()->with('status', 'rally-assignment-responded');
    }

    public function participation(Request $request, string $occurrence, string $assignment, EventCalendarQuery $events, RecordRallyParticipation $recordParticipation): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $assignmentRecord = RallyAssignment::query()
            ->whereKey($assignment)
            ->whereHas('rallyGroup', static fn ($query) => $query->where('occurrence_id', $record->id))
            ->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::in([RallyAssignmentStatus::Participated->value, RallyAssignmentStatus::Absent->value])],
        ]);
        $recordParticipation->handle($actor, $assignmentRecord, RallyAssignmentStatus::from((string) $validated['status']));

        return back()->with('status', 'rally-participation-recorded');
    }

    /** @return array<string,mixed> */
    private function validateRecommendedFormation(Request $request): array
    {
        return $request->validate([
            'alliance_id' => ['required', 'string'],
            'guidance_rule_id' => ['nullable', 'string'],
            'key' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'assignment_role' => ['nullable', Rule::enum(RallyAssignmentRole::class)],
            'infantry_percent' => ['required', 'integer', 'between:0,100'],
            'cavalry_percent' => ['required', 'integer', 'between:0,100'],
            'archer_percent' => ['required', 'integer', 'between:0,100'],
            'heroes' => ['nullable', 'array', 'max:5'],
            'heroes.*' => ['string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /** @return array<string,mixed> */
    private function validateGroup(Request $request): array
    {
        return $request->validate([
            'alliance_id' => ['required', 'string'],
            'recommended_formation_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'max_joiners' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /** @param array<string,mixed> $validated */
    private function composition(array $validated): FormationComposition
    {
        return new FormationComposition((int) $validated['infantry_percent'], (int) $validated['cavalry_percent'], (int) $validated['archer_percent']);
    }

    private function player(): Player
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before performing Rally operations.');

        return $player;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
