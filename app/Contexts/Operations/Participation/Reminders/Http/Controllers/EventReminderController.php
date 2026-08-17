<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Participation\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Actions\DisableEventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventReminderController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function store(
        Request $request,
        string $event,
        EventCalendarQuery $events,
        CreateEventReminderRule $create,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'minutes_before' => ['required', 'integer', 'between:1,10080'],
            'audience' => ['required', Rule::enum(EventReminderAudience::class)],
        ]);
        $create->handle(
            actor: $actor,
            event: $events->eventForManage($actor, $event),
            minutesBefore: (int) $validated['minutes_before'],
            audience: EventReminderAudience::from((string) $validated['audience']),
        );

        return back()->with('status', 'event-reminder-created');
    }

    public function destroy(
        Request $request,
        string $event,
        string $rule,
        EventCalendarQuery $events,
        DisableEventReminderRule $disable,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $events->eventForManage($actor, $event);
        $reminder = EventReminderRule::query()
            ->whereKey($rule)
            ->where('event_id', $record->id)
            ->firstOrFail();
        $disable->handle($actor, $record, $reminder);

        return back()->with('status', 'event-reminder-disabled');
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
