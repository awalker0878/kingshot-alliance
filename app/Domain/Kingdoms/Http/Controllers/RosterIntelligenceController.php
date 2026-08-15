<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Domain\Kingdoms\Services\RosterIntelligence;
use App\Shared\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RosterIntelligenceController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RosterIntelligence $intelligence,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($context->player(), $alliance, AlliancePermission::View)) {
            throw new AuthorizationException;
        }

        $canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);
        $metrics = $intelligence->forAlliance($alliance);

        return Inertia::render('Alliance/RosterIntelligence', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
            'canManage' => $canManage,
            'metrics' => [
                ...$metrics,
                'comparisons' => $canManage ? $metrics['comparisons'] : [],
            ],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
