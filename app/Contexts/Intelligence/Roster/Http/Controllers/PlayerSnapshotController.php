<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Intelligence\Roster\Actions\RecordPlayerSnapshot;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PlayerSnapshotController extends Controller
{
    public function store(Request $request, AllianceContext $context, RecordPlayerSnapshot $record, string $entry): RedirectResponse
    {
        /** @var array{observed_name:string,power:string,progression_level?:string|null,observed_alliance_tag?:string|null,captured_at:string} $validated */
        $validated = $request->validate([
            'observed_name' => ['required', 'string', 'max:160'],
            'power' => ['required', 'string', 'regex:/^\d{1,19}$/'],
            'progression_level' => ['nullable', 'string', 'max:64'],
            'observed_alliance_tag' => ['nullable', 'string', 'max:32'],
            'captured_at' => ['required', 'date'],
        ]);
        $scope = $context->scope();
        $record->handle($scope->allianceId, $scope->playerId, $entry, $validated);

        return back()->with('actionReceipt', $this->receipt('player-snapshot-recorded'));
    }
}
