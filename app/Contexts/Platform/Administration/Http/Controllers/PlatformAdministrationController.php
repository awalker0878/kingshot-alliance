<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use App\Contexts\Platform\Administration\Queries\PlatformAdministrationQuery;
use App\Contexts\Platform\AllianceAdministration\Actions\ConfigureAlliancePlatform;
use App\Contexts\Platform\AllianceAdministration\Actions\ManageAllianceLifecycle;
use App\Contexts\Platform\AllianceAdministration\Services\AllianceFeatureService;
use App\Contexts\Platform\AllianceAdministration\Services\PlatformUsageService;
use App\Contexts\Platform\DataGovernance\Models\LegalHold;
use App\Contexts\Platform\DataGovernance\Services\AllianceDataExportService;
use App\Contexts\Platform\DataGovernance\Services\LegalHoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class PlatformAdministrationController
{
    public function __construct(private readonly AccountIdentityQuery $accounts) {}

    public function index(
        Request $request,
        PlatformAdministrationQuery $query,
        AllianceFeatureService $features,
    ): Response {
        $account = $this->account($request);
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

    public function grantAdministrator(Request $request, ManagePlatformAdministrator $manage): RedirectResponse
    {
        $actor = $this->account($request);
        $validated = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $targetUserId = $this->accounts->findIdByEmail((string) $validated['email']);

        if ($targetUserId === null) {
            throw ValidationException::withMessages(['email' => 'No account exists for that email address.']);
        }

        $manage->grant($targetUserId, $actor);

        return back()->with('status', 'platform-administrator-granted');
    }

    public function revokeAdministrator(
        Request $request,
        string $administrator,
        ManagePlatformAdministrator $manage,
    ): RedirectResponse {
        $actor = $this->account($request);
        $grant = PlatformAdministrator::query()->findOrFail($administrator);

        try {
            $manage->revoke($actor, $grant);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['administrator' => $exception->getMessage()]);
        }

        return back()->with('status', 'platform-administrator-revoked');
    }

    public function lifecycle(
        Request $request,
        string $alliance,
        string $operation,
        ManageAllianceLifecycle $lifecycle,
    ): RedirectResponse {
        $actor = $this->account($request);
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

    public function assignPlan(
        Request $request,
        string $alliance,
        ConfigureAlliancePlatform $configure,
    ): RedirectResponse {
        $actor = $this->account($request);
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
        $actor = $this->account($request);
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
        $actor = $this->account($request);
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
        $actor = $this->account($request);
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
        $actor = $this->account($request);
        $legalHolds->release($actor, LegalHold::query()->findOrFail($hold));

        return back()->with('status', 'legal-hold-released');
    }

    public function captureUsage(Request $request, string $alliance, PlatformUsageService $usage): RedirectResponse
    {
        $this->account($request);
        $usage->capture(Alliance::query()->findOrFail($alliance));

        return back()->with('status', 'alliance-usage-captured');
    }

    public function export(Request $request, string $alliance, AllianceDataExportService $exports): HttpResponse
    {
        $actor = $this->account($request);
        $target = Alliance::query()->findOrFail($alliance);
        $export = $exports->generate($actor, $target);

        return response($export['contents'], 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'X-Export-SHA256' => $export['sha256'],
            'X-Export-Rows' => (string) $export['rowCount'],
        ]);
    }

    private function account(Request $request): AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
