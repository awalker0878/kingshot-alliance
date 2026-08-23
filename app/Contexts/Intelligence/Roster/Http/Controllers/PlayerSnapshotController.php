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
        /** @var array<string,mixed> $validated */
        $validated = $request->validate([
            'observed_name' => ['required', 'string', 'max:160'],
            'power' => ['required', 'string', 'regex:/^\d{1,19}$/'],
            'progression_level' => ['nullable', 'string', 'max:64'],
            'observed_alliance_tag' => ['nullable', 'string', 'max:32'],
            'captured_at' => ['required', 'date'],
            'progression_dataset_id' => ['nullable', 'string', 'max:120'],
            'progression_dataset_checksum' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'hero_observations' => ['nullable', 'array', 'max:34'],
            'hero_observations.*.hero_id' => ['required_with:hero_observations', 'string', 'max:120'],
            'hero_observations.*.level' => ['nullable', 'integer', 'min:0', 'max:80'],
            'hero_observations.*.star' => ['nullable', 'integer', 'min:0', 'max:5'],
            'hero_observations.*.widget_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'hero_observations.*.complete_roster_capture' => ['sometimes', 'boolean'],
        ]);
        $scope = $context->scope();
        $record->handle($scope->allianceId, $scope->playerId, $entry, $validated);

        return back()->with('actionReceipt', $this->receipt('player-snapshot-recorded'));
    }
}
