<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeAcquisitionEffectivenessQuery;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeIngestionHealthQuery;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GiftCodeAcquisitionOperationsController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly GiftCodeIngestionHealthQuery $health,
        private readonly GiftCodeAcquisitionEffectivenessQuery $effectiveness,
        private readonly PlatformAuthorization $platformAuthorization,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $this->account($request);

        return Inertia::render('Platform/GiftCodes/SourceOperations', [
            'user' => ['name' => $actor->name, 'email' => $actor->email],
            'sources' => $this->health->get(100),
            'acquisitionEffectiveness' => $this->effectiveness->get(),
            'canManagePlatformPolicy' => $this->platformAuthorization->allows($actor),
        ]);
    }

    public function evidence(Request $request): Response
    {
        $actor = $this->account($request);
        $sources = array_values(array_filter(
            $this->health->get(100),
            static fn (array $source): bool => ($source['active'] ?? false) === true
                && ($source['manualEvidenceAllowed'] ?? false) === true,
        ));

        return Inertia::render('Platform/GiftCodes/SourceEvidence', [
            'user' => ['name' => $actor->name, 'email' => $actor->email],
            'sources' => $sources,
        ]);
    }

    private function account(Request $request): AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
