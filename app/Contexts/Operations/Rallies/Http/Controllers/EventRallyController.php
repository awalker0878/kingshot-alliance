<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Rallies\Actions\AssignRallyPlayer;
use App\Contexts\Operations\Rallies\Actions\RecordRallyParticipation;
use App\Contexts\Operations\Rallies\Actions\RemoveRallyPlayer;
use App\Contexts\Operations\Rallies\Actions\RespondRallyAssignment;
use App\Contexts\Operations\Rallies\Actions\SaveEventRecommendedFormation;
use App\Contexts\Operations\Rallies\Actions\SaveRallyGroup;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventRallyController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function storeFormation(Request $request, string $occurrence, SaveEventRecommendedFormation $save): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $this->validateRecommendedFormation($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            allianceId: (string) $validated['alliance_id'],
            key: (string) $validated['key'],
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            heroes: $validated['heroes'] ?? [],
            assignmentRole: isset($validated['assignment_role']) ? RallyAssignmentRole::from((string) $validated['assignment_role']) : null,
            guidanceRuleId: isset($validated['guidance_rule_id']) ? (string) $validated['guidance_rule_id'] : null,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'rally-formation-saved');
    }

    public function updateFormation(Request $request, string $occurrence, string $formation, SaveEventRecommendedFormation $save): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $this->validateRecommendedFormation($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            allianceId: (string) $validated['alliance_id'],
            key: (string) $validated['key'],
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            heroes: $validated['heroes'] ?? [],
            assignmentRole: isset($validated['assignment_role']) ? RallyAssignmentRole::from((string) $validated['assignment_role']) : null,
            guidanceRuleId: isset($validated['guidance_rule_id']) ? (string) $validated['guidance_rule_id'] : null,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            formationId: $formation,
        );

        return back()->with('status', 'rally-formation-saved');
    }

    public function storeGroup(Request $request, string $occurrence, SaveRallyGroup $save): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $this->validateGroup($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            allianceId: (string) $validated['alliance_id'],
            name: (string) $validated['name'],
            maxJoiners: isset($validated['max_joiners']) ? (int) $validated['max_joiners'] : null,
            recommendedFormationId: isset($validated['recommended_formation_id']) ? (string) $validated['recommended_formation_id'] : null,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('status', 'rally-group-saved');
    }

    public function updateGroup(Request $request, string $occurrence, string $group, SaveRallyGroup $save): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $this->validateGroup($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            allianceId: (string) $validated['alliance_id'],
            name: (string) $validated['name'],
            maxJoiners: isset($validated['max_joiners']) ? (int) $validated['max_joiners'] : null,
            recommendedFormationId: isset($validated['recommended_formation_id']) ? (string) $validated['recommended_formation_id'] : null,
            notes: $validated['notes'] ?? null,
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            groupId: $group,
        );

        return back()->with('status', 'rally-group-saved');
    }

    public function assign(Request $request, string $occurrence, string $group, string $player, AssignRallyPlayer $assign): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $request->validate([
            'role' => ['required', Rule::enum(RallyAssignmentRole::class)],
            'slot_number' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $assign->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            groupId: $group,
            playerId: $player,
            role: RallyAssignmentRole::from((string) $validated['role']),
            slotNumber: isset($validated['slot_number']) ? (int) $validated['slot_number'] : null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'rally-player-assigned');
    }

    public function remove(Request $request, string $occurrence, string $group, string $player, RemoveRallyPlayer $remove): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $remove->handle($actor->playerId, $occurrence, $group, $player);

        return back()->with('status', 'rally-player-removed');
    }

    public function respond(Request $request, string $occurrence, string $assignment, RespondRallyAssignment $respond): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $request->validate([
            'status' => ['required', Rule::in([RallyAssignmentStatus::Confirmed->value, RallyAssignmentStatus::Declined->value])],
        ]);
        $respond->handle(
            $actor->playerId,
            $occurrence,
            $assignment,
            RallyAssignmentStatus::from((string) $validated['status']),
        );

        return back()->with('status', 'rally-assignment-responded');
    }

    public function participation(Request $request, string $occurrence, string $assignment, RecordRallyParticipation $recordParticipation): RedirectResponse
    {
        $this->authenticated($request);
        $actor = $this->player();
        $validated = $request->validate([
            'status' => ['required', Rule::in([RallyAssignmentStatus::Participated->value, RallyAssignmentStatus::Absent->value])],
        ]);
        $recordParticipation->handle(
            $actor->playerId,
            $occurrence,
            $assignment,
            RallyAssignmentStatus::from((string) $validated['status']),
        );

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

    private function player(): PlayerReference
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Player before performing Rally operations.');

        return $player;
    }

    private function authenticated(Request $request): void
    {
        abort_unless($request->user() !== null, 401);
    }
}
