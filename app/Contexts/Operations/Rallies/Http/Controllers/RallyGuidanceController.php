<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\Rallies\Actions\SaveRallyGuidanceRule;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RallyGuidanceController extends Controller
{
    public function store(Request $request, string $alliance, PlayerContext $context, SaveRallyGuidanceRule $save): RedirectResponse
    {
        $validated = $this->validateRule($request);
        $this->applyRule($save, $context->player()->playerId, $alliance, $validated);

        return back()->with('status', 'rally-guidance-saved');
    }

    public function update(Request $request, string $alliance, string $rule, PlayerContext $context, SaveRallyGuidanceRule $save): RedirectResponse
    {
        $validated = $this->validateRule($request);
        $this->applyRule($save, $context->player()->playerId, $alliance, $validated, $rule);

        return back()->with('status', 'rally-guidance-saved');
    }

    /** @return array<string,mixed> */
    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'infantry_percent' => ['required', 'integer', 'between:0,100'],
            'cavalry_percent' => ['required', 'integer', 'between:0,100'],
            'archer_percent' => ['required', 'integer', 'between:0,100'],
            'hero_recommendations' => ['nullable', 'array', 'max:5'],
            'hero_recommendations.*' => ['string', 'max:120'],
            'lead_requirements' => ['nullable', 'string', 'max:10000'],
            'joiner_guidance' => ['nullable', 'string', 'max:10000'],
            'source' => ['nullable', 'string', 'max:255'],
            'rationale' => ['nullable', 'string', 'max:10000'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string,mixed> $validated */
    private function applyRule(
        SaveRallyGuidanceRule $save,
        string $actorPlayerId,
        string $allianceId,
        array $validated,
        ?string $ruleId = null,
    ): void {
        $save->handle(
            actorPlayerId: $actorPlayerId,
            allianceId: $allianceId,
            name: (string) $validated['name'],
            composition: new FormationComposition((int) $validated['infantry_percent'], (int) $validated['cavalry_percent'], (int) $validated['archer_percent']),
            heroes: $validated['hero_recommendations'] ?? [],
            leadRequirements: $validated['lead_requirements'] ?? null,
            joinerGuidance: $validated['joiner_guidance'] ?? null,
            source: $validated['source'] ?? null,
            rationale: $validated['rationale'] ?? null,
            effectiveFrom: isset($validated['effective_from']) ? CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['effective_from']) : null,
            effectiveUntil: isset($validated['effective_until']) ? CarbonImmutable::createFromFormat('Y-m-d', (string) $validated['effective_until']) : null,
            isActive: (bool) ($validated['is_active'] ?? true),
            ruleId: $ruleId,
        );
    }
}
