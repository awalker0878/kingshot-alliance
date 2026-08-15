<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Contributions\Actions\ApproveContributionRecord;
use App\Domain\Contributions\Actions\CorrectContributionRecord;
use App\Domain\Contributions\Actions\CreateContributionCategory;
use App\Domain\Contributions\Actions\CreateContributionReportSchedule;
use App\Domain\Contributions\Actions\RecordContribution;
use App\Domain\Contributions\Actions\RefreshContributionDataQuality;
use App\Domain\Contributions\Actions\ResolveContributionDataQualityFlag;
use App\Domain\Contributions\Actions\ReverseContributionRecord;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionDataQualityFlag;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Queries\ContributionReportingQuery;
use App\Domain\Contributions\Services\ContributionReportExporter;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Shared\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ContributionController extends Controller
{
    public function index(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionReportingQuery $reports): Response
    {
        $user = $this->user($request);
        $actor = $context->player();
        $alliance = $context->alliance();

        return Inertia::render('Alliance/Contributions/Index', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->id, 'name' => $alliance->name, 'timezone' => $alliance->timezone],
            'player' => ['id' => (string) $actor->id, 'name' => (string) $actor->current_name],
            'canManage' => $authorization->allows($actor, $alliance, PermissionKey::ContributionManage),
            'reporting' => $reports->memberDashboard($alliance, $actor),
        ]);
    }

    public function manage(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionReportingQuery $reports): Response
    {
        [$user, , $alliance] = $this->requireManager($request, $context, $authorization);

        return Inertia::render('Alliance/Contributions/Manage', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->id, 'name' => $alliance->name, 'timezone' => $alliance->timezone],
            'reporting' => $reports->managementDashboard($alliance),
            'periods' => array_column(ContributionPeriod::cases(), 'value'),
            'dataClasses' => array_column(ContributionDataClass::cases(), 'value'),
        ]);
    }

    public function storeSelfReport(Request $request, AllianceContext $context, RecordContribution $record): RedirectResponse
    {
        $actor = $context->player();
        $alliance = $context->alliance();
        $validated = $request->validate([
            'category_id' => ['required', 'string', 'max:26'],
            'value' => ['required', 'numeric', 'min:0'],
            'evidence' => ['nullable', 'string', 'max:4000'],
        ]);
        $category = ContributionCategory::query()->where('alliance_id', $alliance->id)->whereKey((string) $validated['category_id'])->firstOrFail();

        $record->handle($actor, $alliance, $actor, $category, (float) $validated['value'], ContributionRecordSource::SelfReported, $validated['evidence'] ?? null);

        return back()->with('status', 'Contribution submitted for approval.');
    }

    public function storeCategory(Request $request, AllianceContext $context, AllianceAuthorization $authorization, CreateContributionCategory $create): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'unit' => ['required', 'string', 'max:40'],
            'period' => ['required', Rule::enum(ContributionPeriod::class)],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'goal_value' => ['nullable', 'numeric', 'min:0'],
            'evidence_required' => ['required', 'boolean'],
            'allow_self_report' => ['required', 'boolean'],
            'leaderboard_enabled' => ['required', 'boolean'],
            'data_class' => ['required', Rule::enum(ContributionDataClass::class)],
            'calculation_key' => ['nullable', 'string', 'max:80'],
            'calculation_version' => ['nullable', 'string', 'max:40'],
            'calculation_description' => ['nullable', 'string', 'max:4000'],
        ]);

        $create->handle(
            $actor,
            $alliance,
            $validated['name'],
            $validated['unit'],
            ContributionPeriod::from($validated['period']),
            ContributionDataClass::from($validated['data_class']),
            isset($validated['goal_value']) ? (float) $validated['goal_value'] : null,
            (bool) $validated['evidence_required'],
            (bool) $validated['allow_self_report'],
            (bool) $validated['leaderboard_enabled'],
            $validated['description'] ?? null,
            $validated['period_start'] ?? null,
            $validated['period_end'] ?? null,
            $validated['calculation_key'] ?? null,
            $validated['calculation_version'] ?? null,
            $validated['calculation_description'] ?? null,
        );

        return back()->with('status', 'Contribution category created.');
    }

    public function storeManualRecord(Request $request, AllianceContext $context, AllianceAuthorization $authorization, RecordContribution $record): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'ulid'],
            'category_id' => ['required', 'string', 'ulid'],
            'value' => ['required', 'numeric', 'min:0'],
            'evidence' => ['nullable', 'string', 'max:4000'],
        ]);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->where('player_id', (string) $validated['player_id'])
            ->with('player')
            ->firstOrFail();
        $category = ContributionCategory::query()->where('alliance_id', $alliance->id)->whereKey((string) $validated['category_id'])->firstOrFail();

        $record->handle($actor, $alliance, $membership->player, $category, (float) $validated['value'], ContributionRecordSource::Manual, $validated['evidence'] ?? null);

        return back()->with('status', 'Contribution recorded and awaiting approval.');
    }

    public function approve(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionRecord $record, ApproveContributionRecord $approve): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $record = ContributionRecord::query()->where('alliance_id', $alliance->id)->findOrFail($record->id);
        $approve->handle($actor, $alliance, $record);

        return back()->with('status', 'Contribution approved.');
    }

    public function correct(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionRecord $record, CorrectContributionRecord $correct): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $record = ContributionRecord::query()->where('alliance_id', $alliance->id)->findOrFail($record->id);
        $validated = $request->validate(['value' => ['required', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'max:2000'], 'evidence' => ['nullable', 'string', 'max:4000']]);
        $correct->handle($actor, $alliance, $record, (float) $validated['value'], $validated['reason'], $validated['evidence'] ?? null);

        return back()->with('status', 'Contribution correction recorded.');
    }

    public function reverse(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionRecord $record, ReverseContributionRecord $reverse): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $record = ContributionRecord::query()->where('alliance_id', $alliance->id)->findOrFail($record->id);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $reverse->handle($actor, $alliance, $record, $validated['reason']);

        return back()->with('status', 'Contribution reversed.');
    }

    public function refreshQuality(Request $request, AllianceContext $context, AllianceAuthorization $authorization, RefreshContributionDataQuality $refresh): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $result = $refresh->handle($actor, $alliance);

        return back()->with('status', sprintf('Data quality refreshed: %d missing evidence, %d missing records.', $result['missing_evidence'], $result['missing_records']));
    }

    public function resolveQualityFlag(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionDataQualityFlag $flag, ResolveContributionDataQualityFlag $resolve): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $flag = ContributionDataQualityFlag::query()->where('alliance_id', $alliance->id)->findOrFail($flag->id);
        $resolve->handle($actor, $alliance, $flag);

        return back()->with('status', 'Data-quality flag resolved.');
    }

    public function storeReportSchedule(Request $request, AllianceContext $context, AllianceAuthorization $authorization, CreateContributionReportSchedule $create): RedirectResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $validated = $request->validate([
            'recipient_player_id' => ['required', 'string', 'ulid'],
            'name' => ['required', 'string', 'max:120'],
            'cadence' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'timezone' => ['required', 'timezone'],
            'next_due_at' => ['required', 'date'],
        ]);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->where('player_id', (string) $validated['recipient_player_id'])
            ->with('player')
            ->firstOrFail();

        $create->handle($actor, $alliance, $membership->player, $validated['name'], $validated['cadence'], $validated['timezone'], CarbonImmutable::parse($validated['next_due_at'], $validated['timezone']));

        return back()->with('status', 'Contribution report schedule created.');
    }

    public function exportCsv(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionReportExporter $exporter): HttpResponse
    {
        return $this->export($request, $context, $authorization, $exporter, 'csv');
    }

    public function exportSpreadsheet(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionReportExporter $exporter): HttpResponse
    {
        return $this->export($request, $context, $authorization, $exporter, 'spreadsheet');
    }

    private function export(Request $request, AllianceContext $context, AllianceAuthorization $authorization, ContributionReportExporter $exporter, string $format): HttpResponse
    {
        [, $actor, $alliance] = $this->requireManager($request, $context, $authorization);
        $result = $exporter->export($alliance, $actor, $format);

        return response($result['content'], 200, [
            'Content-Type' => $result['mime'],
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'X-Report-Version' => $result['run']->report_version,
            'X-Report-Checksum' => $result['run']->checksum,
        ]);
    }

    /** @return array{User, Player, Alliance} */
    private function requireManager(Request $request, AllianceContext $context, AllianceAuthorization $authorization): array
    {
        $user = $this->user($request);
        $actor = $context->player();
        $alliance = $context->alliance();
        abort_unless($authorization->allows($actor, $alliance, PermissionKey::ContributionManage), 403);

        return [$user, $actor, $alliance];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
