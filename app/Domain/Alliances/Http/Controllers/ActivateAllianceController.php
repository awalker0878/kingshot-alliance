<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActivateAllianceController extends Controller
{
    public function __invoke(
        Request $request,
        string $alliance,
        AuditRecorder $audit,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('alliance')
            ->firstOrFail();

        $sessionKey = (string) config('identity.active_alliance_session_key');
        $previousAllianceId = $request->session()->get($sessionKey);
        $request->session()->put($sessionKey, $membership->alliance_id);

        $audit->record(
            event: 'alliance.context_changed',
            actor: $user,
            subject: $membership->alliance,
            alliance: $membership->alliance,
            metadata: [
                'previous_alliance_id' => is_string($previousAllianceId) ? $previousAllianceId : null,
            ],
        );

        return redirect()->route('alliance.overview');
    }
}
