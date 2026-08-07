<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Identity\AllianceContext;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveAllianceContext
{
    public function __construct(private AllianceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessionKey = (string) config('identity.active_alliance_session_key');
        $activeAllianceId = $request->session()->get($sessionKey);

        if (! is_string($activeAllianceId) || $activeAllianceId === '') {
            abort(409, 'An active alliance is required.');
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $activeAllianceId)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('alliance')
            ->first();

        if ($membership === null) {
            $request->session()->forget($sessionKey);
            abort(403, 'The active alliance is no longer available to this account.');
        }

        $alliance = $membership->alliance;

        if (! $alliance instanceof Alliance) {
            throw new LogicException('An active membership must reference an alliance.');
        }

        $this->context->activate($alliance, $user);
        $request->attributes->set('alliance_id', $membership->alliance_id);
        $request->attributes->set('alliance_membership_id', $membership->id);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
