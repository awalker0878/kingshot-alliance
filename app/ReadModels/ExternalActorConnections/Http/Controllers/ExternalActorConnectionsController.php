<?php

declare(strict_types=1);

namespace App\ReadModels\ExternalActorConnections\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Models\ExternalActorLink;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ExternalActorConnectionsController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly AllianceReferenceQuery $alliances,
    ) {}

    public function __invoke(
        Request $request,
        PlayerContext $players,
        AllianceContext $allianceContext,
    ): Response {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);
        $player = $players->player();
        $scope = $allianceContext->scope();
        $alliance = $this->alliances->require($scope->allianceId);

        return Inertia::render('Accounts/Governor/Connections', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'player' => ['id' => $player->playerId, 'name' => $player->name],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'providers' => array_map(
                static fn (ExternalActorProvider $provider): string => $provider->value,
                ExternalActorProvider::cases(),
            ),
            'links' => ExternalActorLink::query()
                ->where('alliance_id', $alliance->allianceId)
                ->where('player_id', $player->playerId)
                ->latest('verified_at')
                ->limit(20)
                ->get()
                ->map(static fn (ExternalActorLink $link): array => [
                    'id' => (string) $link->id,
                    'provider' => $link->provider->value,
                    'subjectHint' => (string) $link->subject_hint,
                    'verifiedAt' => $link->verified_at->toIso8601String(),
                    'revokedAt' => $link->revoked_at?->toIso8601String(),
                ])->all(),
            'issuedPairing' => $request->session()->get('issued_external_actor_pairing'),
        ]);
    }
}
