<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\ReadModels\PlatformAdministration\PlatformAdministrationQuery;
use App\ReadModels\PlatformAdministration\PlatformCatalogueKind;
use App\ReadModels\PlatformAdministration\PlatformCatalogueQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PlatformAdministrationReadController
{
    public function __construct(
        private AccountIdentityQuery $accounts,
    ) {}

    public function __invoke(
        Request $request,
        PlatformAdministrationQuery $query,
        PlatformCatalogueQuery $catalogues,
    ): Response {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);

        $rules = ['alliance' => ['nullable', 'ulid']];
        foreach (PlatformCatalogueKind::cases() as $kind) {
            $rules[$kind->value.'_cursor'] = ['nullable', 'string', 'max:4096'];
        }
        $validated = $request->validate([...$rules,
            'correlation' => [
                'nullable',
                'string',
                'max:36',
                'regex:/^(?:[0-9a-fA-F]{32}|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})$/',
            ],
        ]);
        $cursors = [];
        foreach (PlatformCatalogueKind::cases() as $kind) {
            $cursors[$kind->value] = $validated[$kind->value.'_cursor'] ?? null;
        }
        $dashboard = $query->dashboard($account->userId, isset($validated['correlation'])
            ? strtolower((string) $validated['correlation']) : null, $cursors);
        $selectedAllianceId = $validated['alliance'] ?? null;
        $selectedAlliance = $selectedAllianceId === null ? null : $catalogues->alliance($account->userId, $selectedAllianceId);
        if ($selectedAlliance !== null) {
            $features = $catalogues->page($account->userId, PlatformCatalogueKind::Features, $cursors['features'], $selectedAllianceId);
            $selectedAlliance['features'] = $features['items'];
            unset($features['items']);
            $selectedAlliance['featuresPagination'] = $features;
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
