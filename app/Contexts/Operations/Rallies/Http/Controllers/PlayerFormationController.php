<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\Rallies\Actions\DeletePlayerFormation;
use App\Contexts\Operations\Rallies\Actions\SavePlayerFormation;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PlayerFormationController extends Controller
{
    public function store(Request $request, PlayerContext $context, SavePlayerFormation $save): RedirectResponse
    {
        $playerId = $context->player()->playerId;
        $validated = $this->validateFormation($request);
        $save->handle(
            actorPlayerId: $playerId,
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            heroes: $validated['heroes'] ?? [],
            notes: $validated['notes'] ?? null,
            isDefault: (bool) ($validated['is_default'] ?? false),
            progressionDatasetId: $validated['progression_dataset_id'] ?? null,
            progressionDatasetChecksum: $validated['progression_dataset_checksum'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('player-formation-saved'));
    }

    public function update(Request $request, string $formation, PlayerContext $context, SavePlayerFormation $save): RedirectResponse
    {
        $playerId = $context->player()->playerId;
        $validated = $this->validateFormation($request);
        $save->handle(
            actorPlayerId: $playerId,
            name: (string) $validated['name'],
            composition: $this->composition($validated),
            heroes: $validated['heroes'] ?? [],
            notes: $validated['notes'] ?? null,
            isDefault: (bool) ($validated['is_default'] ?? false),
            formationId: $formation,
            progressionDatasetId: $validated['progression_dataset_id'] ?? null,
            progressionDatasetChecksum: $validated['progression_dataset_checksum'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('player-formation-saved'));
    }

    public function destroy(Request $request, string $formation, PlayerContext $context, DeletePlayerFormation $delete): RedirectResponse
    {
        $delete->handle($context->player()->playerId, $formation);

        return back()->with('actionReceipt', $this->receipt('player-formation-deleted'));
    }

    /** @return array<string,mixed> */
    private function validateFormation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'infantry_percent' => ['required', 'integer', 'between:0,100'],
            'cavalry_percent' => ['required', 'integer', 'between:0,100'],
            'archer_percent' => ['required', 'integer', 'between:0,100'],
            'heroes' => ['nullable', 'array', 'max:5'],
            'heroes.*' => ['string', 'max:120'],
            'progression_dataset_id' => ['nullable', 'string', 'max:120'],
            'progression_dataset_checksum' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string,mixed> $validated */
    private function composition(array $validated): FormationComposition
    {
        return new FormationComposition((int) $validated['infantry_percent'], (int) $validated['cavalry_percent'], (int) $validated['archer_percent']);
    }
}
