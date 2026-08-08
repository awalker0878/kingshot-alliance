<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CreateAllianceController extends Controller
{
    public function __invoke(Request $request, CreateAlliance $createAlliance): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('alliances', 'slug'),
            ],
            'kingdom' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'language' => ['required', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $alliance = $createAlliance->handle(
            owner: $user,
            name: $validated['name'],
            slug: $validated['slug'],
            kingdom: $validated['kingdom'] ?? null,
            language: $validated['language'],
            timezone: $validated['timezone'],
        );

        $request->session()->put(
            (string) config('identity.active_alliance_session_key'),
            $alliance->id,
        );

        return redirect()->route('alliance.overview');
    }
}
