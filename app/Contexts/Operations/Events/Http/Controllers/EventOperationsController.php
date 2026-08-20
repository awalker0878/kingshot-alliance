<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\SaveEventPhase;
use App\Contexts\Operations\Events\Enums\EventPhaseStatus;
use App\Contexts\Operations\Events\Enums\EventPhaseType;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Participation\Reminders\Actions\SyncEventPollDeadlineReminder;
use App\Contexts\Operations\Polls\Actions\CastEventPollVote;
use App\Contexts\Operations\Polls\Actions\SaveEventPoll;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventOperationsController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function storePhase(Request $request, string $occurrence, EventCalendarQuery $events, SaveEventPhase $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validatePhase($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            key: (string) $validated['key'],
            type: EventPhaseType::from((string) $validated['phase_type']),
            name: (string) $validated['name'],
            startsAt: $this->time($validated['starts_at'] ?? null, $record->event->timezone),
            endsAt: $this->time($validated['ends_at'] ?? null, $record->event->timezone),
            status: EventPhaseStatus::from((string) $validated['status']),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
        );

        return back()->with('actionReceipt', $this->receipt('event-phase-saved'));
    }

    public function updatePhase(Request $request, string $occurrence, string $phase, EventCalendarQuery $events, SaveEventPhase $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validatePhase($request);
        $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            key: (string) $validated['key'],
            type: EventPhaseType::from((string) $validated['phase_type']),
            name: (string) $validated['name'],
            startsAt: $this->time($validated['starts_at'] ?? null, $record->event->timezone),
            endsAt: $this->time($validated['ends_at'] ?? null, $record->event->timezone),
            status: EventPhaseStatus::from((string) $validated['status']),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            phaseId: $phase,
        );

        return back()->with('actionReceipt', $this->receipt('event-phase-saved'));
    }

    public function storePoll(Request $request, string $occurrence, EventCalendarQuery $events, SaveEventPoll $save, SyncEventPollDeadlineReminder $reminders): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $this->validatePoll($request, creating: true);
        $pollId = $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            key: (string) $validated['key'],
            type: EventPollType::from((string) $validated['poll_type']),
            question: (string) $validated['question'],
            opensAt: $this->time($validated['opens_at'] ?? null, $record->event->timezone),
            closesAt: $this->time($validated['closes_at'] ?? null, $record->event->timezone),
            status: EventPollStatus::from((string) $validated['status']),
            maxChoices: (int) $validated['max_choices'],
            options: $this->options($validated['options'] ?? []),
            settings: ['deadline_reminder_minutes' => $validated['deadline_reminder_minutes'] ?? null],
        );
        $reminders->handle($actor->playerId, $pollId);

        return back()->with('actionReceipt', $this->receipt('event-poll-saved'));
    }

    public function updatePoll(Request $request, string $occurrence, string $poll, EventCalendarQuery $events, SaveEventPoll $save, SyncEventPollDeadlineReminder $reminders): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $pollRecord = EventPoll::query()->whereKey($poll)->where('occurrence_id', $occurrence)->firstOrFail();
        $validated = $this->validatePoll($request, creating: false);
        $pollId = $save->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            key: (string) $validated['key'],
            type: EventPollType::from((string) $validated['poll_type']),
            question: isset($validated['question']) ? (string) $validated['question'] : null,
            questionKey: $pollRecord->question_key,
            opensAt: $this->time($validated['opens_at'] ?? null, $record->event->timezone),
            closesAt: $this->time($validated['closes_at'] ?? null, $record->event->timezone),
            status: EventPollStatus::from((string) $validated['status']),
            maxChoices: (int) $validated['max_choices'],
            options: array_key_exists('options', $validated) ? $this->options($validated['options']) : null,
            settings: array_replace($pollRecord->settings ?? [], ['deadline_reminder_minutes' => $validated['deadline_reminder_minutes'] ?? null]),
            pollId: $poll,
        );
        $reminders->handle($actor->playerId, $pollId);

        return back()->with('actionReceipt', $this->receipt('event-poll-saved'));
    }

    public function vote(Request $request, string $occurrence, string $poll, CastEventPollVote $vote): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'option_ids' => ['required', 'array', 'min:1', 'max:20'],
            'option_ids.*' => ['required', 'string'],
        ]);
        $vote->handle($actor->playerId, $occurrence, $poll, $this->optionIds($validated['option_ids']));

        return back()->with('actionReceipt', $this->receipt('event-poll-vote-saved'));
    }

    /** @return array<string,mixed> */
    private function validatePhase(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:160'],
            'phase_type' => ['required', Rule::enum(EventPhaseType::class)],
            'starts_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'status' => ['required', Rule::enum(EventPhaseStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    /** @return array<string,mixed> */
    private function validatePoll(Request $request, bool $creating): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'poll_type' => ['required', Rule::enum(EventPollType::class)],
            'question' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'opens_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'closes_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'status' => ['required', Rule::enum(EventPollStatus::class)],
            'max_choices' => ['required', 'integer', 'between:1,20'],
            'deadline_reminder_minutes' => ['nullable', 'integer', 'between:1,10080'],
            'options' => ['sometimes', 'array', 'max:50'],
            'options.*.label' => ['required_with:options', 'string', 'max:180'],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
        ]);
    }

    private function time(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $timezone);
    }

    /** @return list<array{label: string, value: string, metadata?: array<string, mixed>}> */
    private function options(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $options = [];
        foreach ($value as $option) {
            if (! is_array($option)) {
                continue;
            }
            $options[] = ['label' => (string) ($option['label'] ?? ''), 'value' => (string) ($option['value'] ?? '')];
        }

        return $options;
    }

    /** @return list<string> */
    private function optionIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $id) {
            if (is_string($id) || is_int($id)) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
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
