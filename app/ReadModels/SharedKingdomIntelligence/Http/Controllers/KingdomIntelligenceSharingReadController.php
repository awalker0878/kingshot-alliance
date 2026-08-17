<?php

declare(strict_types=1);

namespace App\ReadModels\SharedKingdomIntelligence\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Sharing\Queries\KingdomIntelligenceSharingManageQuery;
use App\ReadModels\SharedKingdomIntelligence\SharedKingdomIntelligenceCurrentQuery;
use App\ReadModels\SharedKingdomIntelligence\SharedKingdomIntelligenceHistoryQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomIntelligenceSharingReadController extends Controller
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
        SharedKingdomIntelligenceCurrentQuery $current,
        SharedKingdomIntelligenceHistoryQuery $history,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $alliance = $this->alliances->require($scope->allianceId);
        $validated = $request->validate([
            'target' => ['sometimes', 'nullable', 'ulid'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ]);
        $target = $validated['target'] ?? null;
        $cursor = $validated['cursor'] ?? null;
        if ($cursor !== null && $target === null) {
            throw ValidationException::withMessages([
                'cursor' => 'A shared history cursor requires its target.',
            ]);
        }

        return Inertia::render('Intelligence/Sharing/Index', [
            'user' => $this->userSummary($this->account($request)),
            'alliance' => $this->allianceSummary($alliance->allianceId, $alliance->name, $alliance->kingdomId),
            'canManage' => $authorization->allows(
                $scope->playerId,
                $scope->allianceId,
                IntelligencePermission::KingdomManage,
            ),
            'current' => $current->forRecipient($alliance),
            'selectedHistory' => $target === null
                ? null
                : $history->forRecipientTarget($alliance, (string) $target, is_string($cursor) ? $cursor : null),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomIntelligenceSharingManageQuery $sharing,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        $alliance = $this->alliances->require($scope->allianceId);

        return Inertia::render('Intelligence/Sharing/Manage', [
            'user' => $this->userSummary($this->account($request)),
            'alliance' => $this->allianceSummary($alliance->allianceId, $alliance->name, $alliance->kingdomId),
            'passwordConfirmUrl' => route('password.confirm'),
            'sharing' => $sharing->forAlliance($alliance),
        ]);
    }

    /** @return array{name:string,email:string} */
    private function userSummary(AccountIdentity $account): array
    {
        return ['name' => $account->name, 'email' => $account->email];
    }

    /** @return array{id:string,name:string,kingdom:string|null} */
    private function allianceSummary(string $allianceId, string $name, string $kingdomId): array
    {
        $kingdom = $this->kingdoms->find($kingdomId);

        return [
            'id' => $allianceId,
            'name' => $name,
            'kingdom' => $kingdom === null ? null : (string) $kingdom->number,
        ];
    }

    private function account(Request $request): AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
