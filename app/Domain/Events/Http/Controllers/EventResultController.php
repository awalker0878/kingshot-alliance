<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Events\Actions\SaveEventPlayerResult;
use App\Domain\Events\Actions\SaveEventResult;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EventResultController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function saveOccurrence(Request $request, string $occurrence, EventCalendarQuery $events, SaveEventResult $save): RedirectResponse
    {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $validated = $request->validate([
            'outcome' => ['nullable', 'string', 'max:80'],
            'score' => ['nullable', 'integer', 'min:0'],
            'opponent_score' => ['nullable', 'integer', 'min:0'],
            'rank' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $save->handle(
            $actor,
            $record,
            outcome: $validated['outcome'] ?? null,
            score: isset($validated['score']) ? (int) $validated['score'] : null,
            opponentScore: isset($validated['opponent_score']) ? (int) $validated['opponent_score'] : null,
            rank: isset($validated['rank']) ? (int) $validated['rank'] : null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'event-result-saved');
    }

    public function savePlayer(
        Request $request,
        string $occurrence,
        string $player,
        EventCalendarQuery $events,
        EventParticipantAuthorization $authorization,
        SaveEventPlayerResult $save,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $authorization->authorizeManager($actor, $record->event);
        $playerRecord = Player::query()->whereKey($player)->firstOrFail();
        $validated = $request->validate([
            'outcome' => ['nullable', 'string', 'max:80'],
            'score' => ['nullable', 'integer', 'min:0'],
            'rank' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $save->handle(
            $actor,
            $record,
            $playerRecord,
            outcome: $validated['outcome'] ?? null,
            score: isset($validated['score']) ? (int) $validated['score'] : null,
            rank: isset($validated['rank']) ? (int) $validated['rank'] : null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'event-player-result-saved');
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
