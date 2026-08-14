<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateAllianceController extends Controller
{
    public function __invoke(
        Request $request,
        CreateAlliance $createAlliance,
        PlayerContext $players,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $player = $players->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before creating an Alliance.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('alliances', 'slug'),
            ],
            'language' => ['required', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $createAlliance->handle(
            owner: $player,
            name: $validated['name'],
            slug: $validated['slug'],
            language: $validated['language'],
            timezone: $validated['timezone'],
        );

        return redirect()->route('alliance.overview');
    }
}
