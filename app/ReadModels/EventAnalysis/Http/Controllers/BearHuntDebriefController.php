<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventTargetResolver;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class BearHuntDebriefController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function __invoke(
        Request $request,
        string $occurrence,
        EventCalendarQuery $events,
        EventAuthorization $authorization,
        EventTargetResolver $targets,
        BearHuntDebriefQuery $debrief,
    ): Response {
        $user = $this->user($request);
        $actor = $this->player();
        $eventOccurrence = $events->occurrence($actor, $occurrence);
        $event = $eventOccurrence->event;
        if (! $event instanceof Event) {
            throw new LogicException('A Bear Hunt occurrence must reference an Event.');
        }

        abort_unless((string) $event->eventType->slug === 'bear-hunt', 404);
        $target = $targets->forEvent($event);
        $canManage = $authorization->allows(
            $actor->playerId,
            $event->scopeEnum(),
            $target->targetId,
            OperationsPermission::from((string) $event->typeScope->manage_permission_key),
        );
        $payload = $debrief->forOccurrence($eventOccurrence, $actor, $canManage);

        Log::info('bear_hunt.debrief.viewed', [
            'occurrence_id' => (string) $eventOccurrence->id,
            'alliance_id' => (string) $event->alliance_id,
            'results_available' => (bool) ($payload['summary']['resultsAvailable'] ?? false),
            'governor_count' => (int) ($payload['summary']['governorCount'] ?? 0),
            'attendance_available' => (bool) ($payload['summary']['attendance']['available'] ?? false),
            'rally_data_available' => (bool) ($payload['summary']['rallies']['available'] ?? false),
            'unmatched_governor_count' => (int) ($payload['summary']['unmatchedGovernorCount'] ?? 0),
            'history_count' => is_array($payload['runs'] ?? null) ? count($payload['runs']) : 0,
            'can_review_evidence' => $canManage,
        ]);

        return Inertia::render('Operations/Events/BearHuntDebrief', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'userTimezone' => (string) ($user->timezone ?: 'UTC'),
            'debrief' => $payload,
        ]);
    }

    private function player(): PlayerReference
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Player before opening Bear Hunt Debrief.');

        return $player;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
