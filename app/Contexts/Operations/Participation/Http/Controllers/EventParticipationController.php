<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Participation\Actions\CancelEventRegistration;
use App\Contexts\Operations\Participation\Actions\RecordEventAttendance;
use App\Contexts\Operations\Participation\Actions\RegisterForEvent;
use App\Contexts\Operations\Participation\Actions\RespondToEvent;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventParticipationController extends Controller
{
    public function respond(
        Request $request,
        string $occurrence,
        PlayerContext $context,
        RespondToEvent $respond,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->activePlayer($context);
        $validated = $request->validate([
            'response' => ['required', Rule::enum(EventResponseChoice::class)],
            'preferred_role' => ['nullable', 'string', 'max:64'],
            'preferred_team' => ['nullable', 'string', 'max:64'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $respond->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            response: EventResponseChoice::from((string) $validated['response']),
            preferredRole: $validated['preferred_role'] ?? null,
            preferredTeam: $validated['preferred_team'] ?? null,
            availableFrom: isset($validated['available_from']) ? CarbonImmutable::parse((string) $validated['available_from']) : null,
            availableUntil: isset($validated['available_until']) ? CarbonImmutable::parse((string) $validated['available_until']) : null,
            note: $validated['note'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('event-response-updated'));
    }

    public function register(
        Request $request,
        string $occurrence,
        PlayerContext $context,
        RegisterForEvent $register,
    ): RedirectResponse {
        $this->user($request);
        $register->handle($this->activePlayer($context)->playerId, $occurrence);

        return back()->with('actionReceipt', $this->receipt('event-registration-updated'));
    }

    public function cancelRegistration(
        Request $request,
        string $occurrence,
        PlayerContext $context,
        CancelEventRegistration $cancel,
    ): RedirectResponse {
        $this->user($request);
        $cancel->handle($this->activePlayer($context)->playerId, $occurrence);

        return back()->with('actionReceipt', $this->receipt('event-registration-cancelled'));
    }

    public function attendance(
        Request $request,
        string $occurrence,
        string $player,
        PlayerContext $context,
        RecordEventAttendance $attendance,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->activePlayer($context);
        $validated = $request->validate([
            'status' => ['required', Rule::enum(EventAttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $attendance->handle(
            actorPlayerId: $actor->playerId,
            occurrenceId: $occurrence,
            playerId: $player,
            status: EventAttendanceStatus::from((string) $validated['status']),
            notes: $validated['notes'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('event-attendance-updated'));
    }

    private function activePlayer(PlayerContext $context): PlayerReference
    {
        $player = $context->playerOrNull();
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
