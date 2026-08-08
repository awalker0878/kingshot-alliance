<?php

declare(strict_types=1);

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Actions\ConfigureAlliancePlatform;
use App\Domain\Platform\Actions\ManageAllianceLifecycle;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use App\Domain\Platform\Actions\TransferAllianceOwnership;
use App\Domain\Platform\Models\LegalHold;
use App\Domain\Platform\Models\PlatformAdministrator;
use App\Domain\Platform\Queries\PlatformAdministrationQuery;
use App\Domain\Platform\Services\AllianceDataExportService;
use App\Domain\Platform\Services\AllianceFeatureService;
use App\Domain\Platform\Services\AlliancePlatformDefaultsProvisioner;
use App\Domain\Platform\Services\LegalHoldService;
use App\Domain\Platform\Services\PlatformUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class PlatformAdministrationController extends Controller
{
    public function index(
        Request $request,
        PlatformAdministrationQuery $query,
        AllianceFeatureService $features,
    ): Response {
        $user = $this->user($request);
        $dashboard = $query->dashboard();
        $selectedAllianceId = $request->query('alliance');
        $selectedAlliance = null;

        if (is_string($selectedAllianceId) && $selectedAllianceId !== '') {
            $alliance = Alliance::query()->find($selectedAllianceId);
            if ($alliance instanceof Alliance) {
                $selectedAlliance = [
                    'id' => (string) $alliance->id,
                    'name' => (string) $alliance->name,
                    'features' => $features->all($alliance),
                    'members' => AllianceMembership::query()
                        ->where('alliance_id', $alliance->id)
                        ->with('user:id,name,email')
                        ->orderBy('joined_at')
                        ->get()
                        ->map(static fn (AllianceMembership $membership): array => [
                            'id' => (string) $membership->id,
                            'name' => $membership->user?->name,
                            'email' => $membership->user?->email,
                            'status' => $membership->status->value,
                        ])->all(),
                ];
            }
        }

        return Inertia::render('Platform/Administration/Index', [
            'platform' => $dashboard,
            'selectedAlliance' => $selectedAlliance,
            'currentUserId' => (int) $user->id,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function grantAdministrator(Request $request, ManagePlatformAdministrator $manage): RedirectResponse
    {
        $actor = $this->user($request);
        $validated = $request->validate(['email' => ['required', 'email', 'max:254', 'exists:users,email']]);
        $target = User::query()->where('email', Str::lower((string) $validated['email']))->firstOrFail();
        $manage->grant($target, $actor);

        return back()->with('status', 'platform-administrator-granted');
    }

    public function revokeAdministrator(
        Request $request,
        string $administrator,
        ManagePlatformAdministrator $manage,
    ): RedirectResponse {
        $actor = $this->user($request);
        $grant = PlatformAdministrator::query()->findOrFail($administrator);

        try {
            $manage->revoke($actor, $grant);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['administrator' => $exception->getMessage()]);
        }

        return back()->with('status', 'platform-administrator-revoked');
    }

    public function provisionAlliance(
        Request $request,
        CreateAlliance $createAlliance,
        AlliancePlatformDefaultsProvisioner $defaults,
    ): RedirectResponse {
        $actor = $this->user($request);
        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        $validated = $request->validate([
            'owner_email' => ['required', 'email', 'exists:users,email'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:alliances,slug'],
            'kingdom' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'language' => ['required', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);
        $owner = User::query()->where('email', Str::lower((string) $validated['owner_email']))->firstOrFail();
        $alliance = $createAlliance->handle(
            $owner,
            (string) $validated['name'],
            (string) $validated['slug'],
            $validated['kingdom'] ?? null,
            (string) $validated['language'],
            (string) $validated['timezone'],
        );
        $defaults->provision($alliance, $actor);

        return redirect()->route('platform.administration.index', ['alliance' => $alliance->id])
            ->with('status', 'alliance-provisioned');
    }

    public function lifecycle(
        Request $request,
        string $alliance,
        string $operation,
        ManageAllianceLifecycle $lifecycle,
    ): RedirectResponse {
        $actor = $this->user($request);
        $target = Alliance::query()->findOrFail($alliance);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            match ($operation) {
                'suspend' => $lifecycle->suspend($actor, $target, (string) $validated['reason']),
                'close' => $lifecycle->close($actor, $target, (string) $validated['reason']),
                'delete' => $lifecycle->delete($actor, $target, (string) $validated['reason']),
                'restore' => $lifecycle->restore($actor, $target, (string) $validated['reason']),
                default => throw new InvalidArgumentException('Unsupported alliance lifecycle operation.'),
            };
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lifecycle' => $exception->getMessage()]);
        }

        return back()->with('status', 'alliance-lifecycle-updated');
    }

    public function transferOwnership(
        Request $request,
        string $alliance,
        TransferAllianceOwnership $transfer,
    ): RedirectResponse {
        $actor = $this->user($request);
        $targetAlliance = Alliance::query()->findOrFail($alliance);
        $validated = $request->validate([
            'membership_id' => ['required', 'string', 'size:26'],
        ]);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $targetAlliance->id)
            ->findOrFail((string) $validated['membership_id']);

        try {
            $transfer->handle($actor, $targetAlliance, $membership);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ownership' => $exception->getMessage()]);
        }

        return back()->with('status', 'alliance-ownership-transferred');
    }

    public function assignPlan(
        Request $request,
        string $alliance,
        ConfigureAlliancePlatform $configure,
    ): RedirectResponse {
        $actor = $this->user($request);
        $target = Alliance::query()->findOrFail($alliance);
        $validated = $request->validate(['plan_code' => ['required', 'string', 'max:40']]);

        try {
            $configure->assignPlan($actor, $target, (string) $validated['plan_code']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['plan' => $exception->getMessage()]);
        }

        return back()->with('status', 'alliance-plan-updated');
    }

    public function updateSettings(
        Request $request,
        string $alliance,
        ConfigureAlliancePlatform $configure,
    ): RedirectResponse {
        $actor = $this->user($request);
        $target = Alliance::query()->findOrFail($alliance);
        $validated = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'queue_partition' => ['required', Rule::in(['standard', 'high-volume', 'maintenance-sensitive'])],
            'api_access_enabled' => ['required', 'boolean'],
            'webhooks_enabled' => ['required', 'boolean'],
        ]);
        $configure->updateSettings(
            $actor,
            $target,
            (int) $validated['retention_days'],
            (string) $validated['queue_partition'],
            (bool) $validated['api_access_enabled'],
            (bool) $validated['webhooks_enabled'],
        );

        return back()->with('status', 'alliance-platform-settings-updated');
    }

    public function setFeature(
        Request $request,
        string $alliance,
        ConfigureAlliancePlatform $configure,
    ): RedirectResponse {
        $actor = $this->user($request);
        $target = Alliance::query()->findOrFail($alliance);
        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:100'],
            'enabled' => ['required', 'boolean'],
            'configuration' => ['nullable', 'array'],
        ]);

        try {
            $configure->setFeature(
                $actor,
                $target,
                (string) $validated['feature_key'],
                (bool) $validated['enabled'],
                isset($validated['configuration']) && is_array($validated['configuration']) ? $validated['configuration'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['feature' => $exception->getMessage()]);
        }

        return back()->with('status', 'alliance-feature-updated');
    }

    public function placeLegalHold(Request $request, LegalHoldService $legalHolds): RedirectResponse
    {
        $actor = $this->user($request);
        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(['user', 'alliance'])],
            'subject_id' => ['required', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $legalHolds->place($actor, (string) $validated['subject_type'], (string) $validated['subject_id'], (string) $validated['reason']);

        return back()->with('status', 'legal-hold-placed');
    }

    public function releaseLegalHold(Request $request, string $hold, LegalHoldService $legalHolds): RedirectResponse
    {
        $actor = $this->user($request);
        $legalHolds->release($actor, LegalHold::query()->findOrFail($hold));

        return back()->with('status', 'legal-hold-released');
    }

    public function captureUsage(Request $request, string $alliance, PlatformUsageService $usage): RedirectResponse
    {
        $this->user($request);
        $usage->capture(Alliance::query()->findOrFail($alliance));

        return back()->with('status', 'alliance-usage-captured');
    }

    public function export(Request $request, string $alliance, AllianceDataExportService $exports): HttpResponse
    {
        $actor = $this->user($request);
        $target = Alliance::query()->findOrFail($alliance);
        $export = $exports->generate($actor, $target);

        return response($export['contents'], 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'X-Export-SHA256' => $export['sha256'],
            'X-Export-Rows' => (string) $export['rowCount'],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
