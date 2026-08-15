<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Events\Actions\AssignEventObjectiveTarget;
use App\Domain\Events\Actions\RemoveEventObjectiveAssignment;
use App\Domain\Events\Actions\SaveEventObjective;
use App\Domain\Events\Enums\EventObjectiveStatus;
use App\Domain\Events\Models\EventObjective;
use App\Domain\Events\Models\EventObjectiveAssignment;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Shared\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventBattlePlanController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function storeObjective(Request $request, string $occurrence, EventCalendarQuery $events, SaveEventObjective $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validateObjective($request);
        $parent = isset($validated['parent_id']) && $validated['parent_id'] !== null
            ? EventObjective::query()->whereKey((string) $validated['parent_id'])->where('occurrence_id', $record->id)->firstOrFail()
            : null;

        $save->handle(
            actor: $actor,
            occurrence: $record,
            name: (string) $validated['name'],
            objectiveType: (string) ($validated['objective_type'] ?? 'custom'),
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            priority: (int) ($validated['priority'] ?? 50),
            startsAt: $this->time($validated['starts_at'] ?? null, $record->event->timezone),
            endsAt: $this->time($validated['ends_at'] ?? null, $record->event->timezone),
            status: EventObjectiveStatus::from((string) ($validated['status'] ?? EventObjectiveStatus::Planned->value)),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parent: $parent,
        );

        return back()->with('status', 'event-objective-saved');
    }

    public function updateObjective(Request $request, string $occurrence, string $objective, EventCalendarQuery $events, SaveEventObjective $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $objectiveRecord = EventObjective::query()->whereKey($objective)->where('occurrence_id', $record->id)->firstOrFail();
        $validated = $this->validateObjective($request);
        $parent = isset($validated['parent_id']) && $validated['parent_id'] !== null
            ? EventObjective::query()->whereKey((string) $validated['parent_id'])->where('occurrence_id', $record->id)->firstOrFail()
            : null;

        $save->handle(
            actor: $actor,
            occurrence: $record,
            name: (string) $validated['name'],
            objectiveType: (string) ($validated['objective_type'] ?? 'custom'),
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            priority: (int) ($validated['priority'] ?? 50),
            startsAt: $this->time($validated['starts_at'] ?? null, $record->event->timezone),
            endsAt: $this->time($validated['ends_at'] ?? null, $record->event->timezone),
            status: EventObjectiveStatus::from((string) ($validated['status'] ?? EventObjectiveStatus::Planned->value)),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parent: $parent,
            objective: $objectiveRecord,
        );

        return back()->with('status', 'event-objective-saved');
    }

    public function assignPlayer(Request $request, string $occurrence, string $objective, string $player, EventCalendarQuery $events, AssignEventObjectiveTarget $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $objectiveRecord = EventObjective::query()->whereKey($objective)->where('occurrence_id', $record->id)->firstOrFail();
        $playerRecord = Player::query()->whereKey($player)->firstOrFail();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $assign->handle($actor, $objectiveRecord, $playerRecord, $validated['notes'] ?? null);

        return back()->with('status', 'event-objective-assigned');
    }

    public function assignRoster(Request $request, string $occurrence, string $objective, string $roster, EventCalendarQuery $events, AssignEventObjectiveTarget $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $objectiveRecord = EventObjective::query()->whereKey($objective)->where('occurrence_id', $record->id)->firstOrFail();
        $rosterRecord = EventRoster::query()->whereKey($roster)->where('occurrence_id', $record->id)->firstOrFail();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $assign->handle($actor, $objectiveRecord, $rosterRecord, $validated['notes'] ?? null);

        return back()->with('status', 'event-objective-assigned');
    }

    public function removeAssignment(Request $request, string $occurrence, string $assignment, EventCalendarQuery $events, RemoveEventObjectiveAssignment $remove): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $assignmentRecord = EventObjectiveAssignment::query()
            ->whereKey($assignment)
            ->where('occurrence_id', $record->id)
            ->firstOrFail();
        $remove->handle($actor, $assignmentRecord);

        return back()->with('status', 'event-objective-assignment-removed');
    }

    /** @return array<string,mixed> */
    private function validateObjective(Request $request): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'string'],
            'objective_type' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'between:0,100'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'status' => ['nullable', Rule::enum(EventObjectiveStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    private function time(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $timezone);
    }

    private function player(): Player
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before performing Event operations.');

        return $player;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
