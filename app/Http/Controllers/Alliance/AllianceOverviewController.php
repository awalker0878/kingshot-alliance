<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alliance;

use App\Application\Identity\AllianceContext;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceOverviewController extends Controller
{
    public function __invoke(AllianceContext $context): Response
    {
        $alliance = $context->alliance();
        $membership = $context->membership()->loadMissing('roles:id,alliance_id,key,name');

        return Inertia::render('Alliance/Overview', [
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $alliance->kingdom,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
            ],
            'membership' => [
                'id' => $membership->id,
                'roles' => $membership->roles->map(static fn ($role): array => [
                    'key' => $role->key,
                    'name' => $role->name,
                ])->values(),
            ],
        ]);
    }
}
