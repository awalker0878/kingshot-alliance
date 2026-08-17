<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomIntelligence\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\ReadModels\KingdomIntelligence\KingdomAllianceIntelligence;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomAllianceIntelligenceController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly AllianceReferenceQuery $alliances,
        private readonly KingdomReferenceQuery $kingdoms,
    ) {}

    public function index(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomAllianceIntelligence $intelligence,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $account = $this->account($request);
        $alliance = $this->alliances->require($scope->allianceId);
        $kingdom = $this->kingdoms->find($alliance->kingdomId);
        $canManage = $authorization->allows(
            $scope->playerId,
            $scope->allianceId,
            IntelligencePermission::KingdomManage,
        );

        return Inertia::render('Alliance/KingdomAllianceIntelligence', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'kingdom' => $kingdom === null ? null : (string) $kingdom->number,
            ],
            'canManage' => $canManage,
            'intelligence' => $intelligence->forAlliance($alliance, $canManage, $this->filters($request)),
        ]);
    }

    /** @return array{tracking:string,freshness:string,diplomacy:string,sort:string,direction:string} */
    private function filters(Request $request): array
    {
        $diplomacyStates = array_map(
            static fn (KingdomAllianceDiplomacyState $state): string => $state->value,
            KingdomAllianceDiplomacyState::cases(),
        );
        $validated = $request->validate([
            'tracking' => ['sometimes', 'string', Rule::in(['all', 'active', 'archived'])],
            'freshness' => ['sometimes', 'string', Rule::in(['all', 'current', 'stale', 'missing'])],
            'diplomacy' => ['sometimes', 'string', Rule::in(['all', ...$diplomacyStates])],
            'sort' => ['sometimes', 'string', Rule::in(['name', 'tag', 'power', 'members', 'age', 'diplomacy'])],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ]);

        return [
            'tracking' => (string) ($validated['tracking'] ?? 'active'),
            'freshness' => (string) ($validated['freshness'] ?? 'all'),
            'diplomacy' => (string) ($validated['diplomacy'] ?? 'all'),
            'sort' => (string) ($validated['sort'] ?? 'name'),
            'direction' => (string) ($validated['direction'] ?? 'asc'),
        ];
    }

    private function account(Request $request): \App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
