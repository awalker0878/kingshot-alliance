<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Contexts\Operations\EventCore\Queries\EventCalendarQuery;
use App\Contexts\Operations\Participation\Actions\CancelEventRegistration;
use App\Contexts\Operations\Participation\Actions\RecordEventAttendance;
use App\Contexts\Operations\Participation\Actions\RegisterForEvent;
use App\Contexts\Operations\Participation\Actions\RespondToEvent;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Shared\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventParticipationController extends Controller
{
    public function respond(
        Request $request,
        string $occurrence,
        EventCalendarQuery $events,
        PlayerContext $context,
        RespondToEvent $respond,
    ): RedirectResponse {
        $this->user($request);
        $player = $this->activePlayer($context);
        $record = $events->occurrence($player, $occurrence);
        $validated = $request->validate([
            'response' => ['required', Rule::enum(EventResponseChoice::class)],
            'preferred_role' => ['nullable', 'string', 'max:64'],
            'preferred_team' => ['nullable', 'string', 'max:64'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $respond->handle(
            actor: $player,
            occurrence: $record,
            player: $player,
            response: EventResponseChoice::from((string) $validated['response']),
            preferredRole: $validated['preferred_role'] ?? null,
            preferredTeam: $validated['preferred_team'] ?? null,
            availableFrom: isset($validated['available_from']) ? CarbonImmutable::parse((string) $validated['available_from']) : null,
            availableUntil: isset($validated['available_until']) ? CarbonImmutable::parse((string) $validated['available_until']) : null,
            note: $validated['note'] ?? null,
        );

        return back()->with('status', 'event-response-updated');
    }

    public function register(
        Request $request,
        string $occurrence,
        EventCalendarQuery $events,
        PlayerContext $context,
        RegisterForEvent $register,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->activePlayer($context);
        $register->handle($actor, $events->occurrence($actor, $occurrence), $actor);

        return back()->with('status', 'event-registration-updated');
    }

    public function cancelRegistration(
        Request $request,
        string $occurrence,
        EventCalendarQuery $events,
        PlayerContext $context,
        CancelEventRegistration $cancel,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->activePlayer($context);
        $cancel->handle($actor, $events->occurrence($actor, $occurrence), $actor);

        return back()->with('status', 'event-registration-cancelled');
    }

    public function attendance(
        Request $request,
        string $occurrence,
        string $player,
        EventCalendarQuery $events,
        PlayerContext $context,
        RecordEventAttendance $attendance,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->activePlayer($context);
        $participant = Player::query()->whereKey($player)->firstOrFail();
        $validated = $request->validate([
            'status' => ['required', Rule::enum(EventAttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $attendance->handle(
            actor: $actor,
            occurrence: $events->occurrence($actor, $occurrence),
            player: $participant,
            status: EventAttendanceStatus::from((string) $validated['status']),
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'event-attendance-updated');
    }

    private function activePlayer(PlayerContext $context): Player
    {
        $player = $context->playerOrNull();
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
