<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Participation\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Actions\DisableEventReminderRule;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventReminderController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function store(Request $request, string $event, CreateEventReminderRule $create): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'minutes_before' => ['required', 'integer', 'between:1,10080'],
            'audience' => ['required', Rule::enum(EventReminderAudience::class)],
        ]);
        $create->handle(
            actorPlayerId: $actor->playerId,
            eventId: $event,
            minutesBefore: (int) $validated['minutes_before'],
            audience: EventReminderAudience::from((string) $validated['audience']),
        );

        return back()->with('status', 'event-reminder-created');
    }

    public function destroy(Request $request, string $event, string $rule, DisableEventReminderRule $disable): RedirectResponse
    {
        $this->user($request);
        $disable->handle($this->player()->playerId, $event, $rule);

        return back()->with('status', 'event-reminder-disabled');
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
