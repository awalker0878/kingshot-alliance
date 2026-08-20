<?php

declare(strict_types=1);

namespace App\Contexts\Operations\BattlePlans\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\BattlePlans\Actions\AssignEventObjectiveTarget;
use App\Contexts\Operations\BattlePlans\Actions\RemoveEventObjectiveAssignment;
use App\Contexts\Operations\BattlePlans\Actions\SaveEventObjective;
use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Shared\Infrastructure\Http\Controller;
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

        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $record->id,
            name: (string) $validated['name'],
            objectiveType: (string) ($validated['objective_type'] ?? 'custom'),
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            priority: (int) ($validated['priority'] ?? 50),
            startsAt: $this->time($validated['starts_at'] ?? null, (string) $record->event->timezone),
            endsAt: $this->time($validated['ends_at'] ?? null, (string) $record->event->timezone),
            status: EventObjectiveStatus::from((string) ($validated['status'] ?? EventObjectiveStatus::Planned->value)),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parentId: isset($validated['parent_id']) ? (string) $validated['parent_id'] : null,
        );

        return back()->with('actionReceipt', $this->receipt('event-objective-saved'));
    }

    public function updateObjective(Request $request, string $occurrence, string $objective, EventCalendarQuery $events, SaveEventObjective $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validateObjective($request);

        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: (string) $record->id,
            name: (string) $validated['name'],
            objectiveType: (string) ($validated['objective_type'] ?? 'custom'),
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            priority: (int) ($validated['priority'] ?? 50),
            startsAt: $this->time($validated['starts_at'] ?? null, (string) $record->event->timezone),
            endsAt: $this->time($validated['ends_at'] ?? null, (string) $record->event->timezone),
            status: EventObjectiveStatus::from((string) ($validated['status'] ?? EventObjectiveStatus::Planned->value)),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            parentId: isset($validated['parent_id']) ? (string) $validated['parent_id'] : null,
            objectiveId: $objective,
        );

        return back()->with('actionReceipt', $this->receipt('event-objective-saved'));
    }

    public function assignPlayer(Request $request, string $occurrence, string $objective, string $player, AssignEventObjectiveTarget $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $assign->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            objectiveId: $objective,
            playerId: $player,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return back()->with('actionReceipt', $this->receipt('event-objective-assigned'));
    }

    public function assignRoster(Request $request, string $occurrence, string $objective, string $roster, AssignEventObjectiveTarget $assign): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:10000']]);
        $assign->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            objectiveId: $objective,
            rosterId: $roster,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
        );

        return back()->with('actionReceipt', $this->receipt('event-objective-assigned'));
    }

    public function removeAssignment(Request $request, string $occurrence, string $assignment, RemoveEventObjectiveAssignment $remove): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $remove->handle($actor->playerId, $occurrence, $assignment);

        return back()->with('actionReceipt', $this->receipt('event-objective-assignment-removed'));
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

    private function player(): PlayerReference
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Player before performing Event operations.');

        return $player;
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }
}
