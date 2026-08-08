<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Http\Controllers;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActivateAllianceController extends Controller
{
    public function __invoke(Request $request, string $alliance, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('alliance')
            ->firstOrFail();
        $target = $membership->alliance;
        abort_unless($target instanceof Alliance && $target->status === AllianceStatus::Active, 403, 'The alliance is not currently active.');

        $sessionKey = (string) config('identity.active_alliance_session_key');
        $previousAllianceId = $request->session()->get($sessionKey);
        $request->session()->put($sessionKey, $membership->alliance_id);

        $audit->record('alliance.context_changed', $user, $target, $target, [
            'previous_alliance_id' => is_string($previousAllianceId) ? $previousAllianceId : null,
        ]);

        return redirect()->route('alliance.overview');
    }
}
