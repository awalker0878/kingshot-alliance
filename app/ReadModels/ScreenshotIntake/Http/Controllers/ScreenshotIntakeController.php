<?php

declare(strict_types=1);

namespace App\ReadModels\ScreenshotIntake\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\ScreenshotIntake\Queries\ScreenshotIntakeWorkspaceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ScreenshotIntakeController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function __invoke(Request $request, string $occurrence, ScreenshotIntakeWorkspaceQuery $workspace): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $actor = $this->playerContext->playerOrNull();
        abort_unless($actor instanceof PlayerReference, 409, 'Select a Player before importing battle reports.');

        return Inertia::render('Operations/Events/Evidence', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'userTimezone' => (string) ($user->timezone ?: 'UTC'),
            'workspace' => $workspace->forBearHunt($actor->playerId, $occurrence),
        ]);
    }
}
