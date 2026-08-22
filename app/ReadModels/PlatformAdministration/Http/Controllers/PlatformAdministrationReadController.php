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

        $validated = $request->validate([
            'correlation' => [
                'nullable',
                'string',
                'max:36',
                'regex:/^(?:[0-9a-fA-F]{32}|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})$/',
            ],
        ]);
        $dashboard = $query->dashboard(isset($validated['correlation'])
            ? strtolower((string) $validated['correlation'])
            : null);
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
        ]);
    }
}
