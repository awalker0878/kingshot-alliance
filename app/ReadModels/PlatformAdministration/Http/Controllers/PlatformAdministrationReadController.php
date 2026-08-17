<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\AllianceAdministration\Services\AllianceFeatureService;
use App\ReadModels\PlatformAdministration\PlatformAdministrationQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PlatformAdministrationReadController
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private AllianceReferenceQuery $alliances,
    ) {}

    public function __invoke(
        Request $request,
        PlatformAdministrationQuery $query,
        AllianceFeatureService $features,
    ): Response {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);

        $dashboard = $query->dashboard();
        $selectedAllianceId = $request->query('alliance');
        $selectedAlliance = null;

        if (is_string($selectedAllianceId) && $selectedAllianceId !== '') {
            $alliance = $this->alliances->find($selectedAllianceId);
            if ($alliance !== null) {
                $selectedAlliance = [
                    'id' => $alliance->allianceId,
                    'name' => $alliance->name,
                    'features' => $features->all($alliance->allianceId),
                ];
            }
        }

        return Inertia::render('Platform/Administration/Index', [
            'user' => [
                'name' => $account->name,
                'email' => $account->email,
            ],
            'platform' => $dashboard,
            'selectedAlliance' => $selectedAlliance,
            'currentUserId' => $account->userId,
            'status' => $request->session()->get('status'),
        ]);
    }
}
