<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Platform\Integrations\Actions\IssueExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Actions\RevokeExternalActorLink;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ExternalActorConnectionController extends Controller
{
    public function issuePairingCode(
        Request $request,
        AllianceContext $context,
        IssueExternalActorPairingCode $issue,
    ): RedirectResponse {
        $validated = $request->validate([
            'provider' => ['required', Rule::enum(ExternalActorProvider::class)],
        ]);
        $scope = $context->scope();
        $issued = $issue->handle(
            $scope->allianceId,
            $scope->playerId,
            ExternalActorProvider::from((string) $validated['provider']),
        );

        return back()
            ->with('issued_external_actor_pairing', [
                'id' => $issued->pairingCodeId,
                'provider' => $issued->provider,
                'code' => $issued->code,
                'expiresAt' => $issued->expiresAt,
            ])
            ->with('actionReceipt', $this->receipt('external-actor-pairing-issued'));
    }

    public function revoke(
        Request $request,
        AllianceContext $context,
        string $link,
        RevokeExternalActorLink $revoke,
    ): RedirectResponse {
        $scope = $context->scope();
        $revoke->handle($scope->allianceId, $scope->playerId, $link);

        return back()->with('actionReceipt', $this->receipt('external-actor-link-revoked'));
    }
}
