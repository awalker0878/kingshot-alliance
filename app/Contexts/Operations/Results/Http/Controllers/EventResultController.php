<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Contexts\Operations\Results\Actions\SaveEventAllianceResult;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use App\Contexts\Operations\Results\Actions\SaveEventResult;
use App\Contexts\Operations\EventCore\Queries\EventCalendarQuery;
use App\Shared\Http\Controller;
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
        $validated = $this->validateResult($request, opponentScore: true);
        $save->handle(
            $actor,
            $record,
            outcome: $validated['outcome'] ?? null,
            score: isset($validated['score']) ? (int) $validated['score'] : null,
            opponentScore: isset($validated['opponent_score']) ? (int) $validated['opponent_score'] : null,
            rank: isset($validated['rank']) ? (int) $validated['rank'] : null,
            notes: $validated['notes'] ?? null,
            metrics: $this->metrics($validated['metrics'] ?? []),
        );

        return back()->with('status', 'event-result-saved');
    }

    public function saveAlliance(
        Request $request,
        string $occurrence,
        string $alliance,
        EventCalendarQuery $events,
        SaveEventAllianceResult $save,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $allianceRecord = Alliance::query()->whereKey($alliance)->firstOrFail();
        $validated = $this->validateResult($request);
        $save->handle(
            $actor,
            $record,
            $allianceRecord,
            outcome: $validated['outcome'] ?? null,
            score: isset($validated['score']) ? (int) $validated['score'] : null,
            rank: isset($validated['rank']) ? (int) $validated['rank'] : null,
            notes: $validated['notes'] ?? null,
            metrics: $this->metrics($validated['metrics'] ?? []),
        );

        return back()->with('status', 'event-alliance-result-saved');
    }

    public function savePlayer(
        Request $request,
        string $occurrence,
        string $player,
        EventCalendarQuery $events,
        SaveEventPlayerResult $save,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $events->occurrence($actor, $occurrence);
        $playerRecord = Player::query()->whereKey($player)->firstOrFail();
        $validated = $this->validateResult($request);
        $save->handle(
            $actor,
            $record,
            $playerRecord,
            outcome: $validated['outcome'] ?? null,
            score: isset($validated['score']) ? (int) $validated['score'] : null,
            rank: isset($validated['rank']) ? (int) $validated['rank'] : null,
            notes: $validated['notes'] ?? null,
            metrics: $this->metrics($validated['metrics'] ?? []),
        );

        return back()->with('status', 'event-player-result-saved');
    }

    /** @return array<string,mixed> */
    private function validateResult(Request $request, bool $opponentScore = false): array
    {
        $rules = [
            'outcome' => ['nullable', 'string', 'max:80'],
            'score' => ['nullable', 'integer', 'min:0'],
            'rank' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'metrics' => ['sometimes', 'array', 'max:100'],
            'metrics.*.key' => ['required_with:metrics', 'string', 'max:96'],
            'metrics.*.dimension_key' => ['nullable', 'string', 'max:96'],
            'metrics.*.value' => ['required_with:metrics', 'numeric'],
        ];

        if ($opponentScore) {
            $rules['opponent_score'] = ['nullable', 'integer', 'min:0'];
        }

        return $request->validate($rules);
    }

    /** @return list<array{key:string,value:int|float|string,dimension_key?:string|null}> */
    private function metrics(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static function (array $metric): array {
            $normalized = [
                'key' => (string) $metric['key'],
                'value' => $metric['value'],
            ];

            if (array_key_exists('dimension_key', $metric)) {
                $normalized['dimension_key'] = $metric['dimension_key'] === null
                    ? null
                    : (string) $metric['dimension_key'];
            }

            return $normalized;
        }, $value));
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
