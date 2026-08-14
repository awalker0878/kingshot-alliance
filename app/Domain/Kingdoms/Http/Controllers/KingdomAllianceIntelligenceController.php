<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomAllianceDiplomacyState;
use App\Domain\Kingdoms\Services\KingdomAllianceIntelligence;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceIntelligenceController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        KingdomAllianceIntelligence $intelligence,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        $canManage = $authorization->allows($context->player(), $alliance, PermissionKey::KingdomManage);

        return Inertia::render('Alliance/KingdomAllianceIntelligence', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => $this->allianceSummary($alliance),
            'canManage' => $canManage,
            'intelligence' => $intelligence->forAlliance(
                $alliance,
                $canManage,
                $this->filters($request),
            ),
        ]);
    }

    /**
     * @return array{tracking: string, freshness: string, diplomacy: string, sort: string, direction: string}
     */
    private function filters(Request $request): array
    {
        $diplomacyStates = array_map(
            static fn (KingdomAllianceDiplomacyState $state): string => $state->value,
            KingdomAllianceDiplomacyState::cases(),
        );

        /** @var array{tracking?: string, freshness?: string, diplomacy?: string, sort?: string, direction?: string} $validated */
        $validated = $request->validate([
            'tracking' => ['sometimes', 'string', Rule::in(['all', 'active', 'archived'])],
            'freshness' => ['sometimes', 'string', Rule::in(['all', 'current', 'stale', 'missing'])],
            'diplomacy' => ['sometimes', 'string', Rule::in(['all', ...$diplomacyStates])],
            'sort' => ['sometimes', 'string', Rule::in(['name', 'tag', 'power', 'members', 'age', 'diplomacy'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ]);

        return [
            'tracking' => $validated['tracking'] ?? 'active',
            'freshness' => $validated['freshness'] ?? 'all',
            'diplomacy' => $validated['diplomacy'] ?? 'all',
            'sort' => $validated['sort'] ?? 'name',
            'direction' => $validated['direction'] ?? 'asc',
        ];
    }

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function allianceSummary(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
