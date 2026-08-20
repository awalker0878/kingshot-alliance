<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Contributions\Actions\ApproveContributionRecord;
use App\Contexts\Intelligence\Contributions\Actions\BulkApproveContributionRecords;
use App\Contexts\Intelligence\Contributions\Actions\CorrectContributionRecord;
use App\Contexts\Intelligence\Contributions\Actions\CreateContributionCategory;
use App\Contexts\Intelligence\Contributions\Actions\CreateContributionReportSchedule;
use App\Contexts\Intelligence\Contributions\Actions\PreviewContributionBulkApproval;
use App\Contexts\Intelligence\Contributions\Actions\RecordContribution;
use App\Contexts\Intelligence\Contributions\Actions\RefreshContributionDataQuality;
use App\Contexts\Intelligence\Contributions\Actions\ResolveContributionDataQualityFlag;
use App\Contexts\Intelligence\Contributions\Actions\ReverseContributionRecord;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Models\ContributionDataQualityFlag;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\Contributions\Queries\ContributionReportingQuery;
use App\Contexts\Intelligence\Contributions\Services\ContributionReportExporter;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ContributionController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceReferenceQuery $alliances,
        PlayerReferenceQuery $players,
        AllianceIntelligenceAuthorization $authorization,
        ContributionReportingQuery $reports,
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $actor = $players->require($scope->playerId);

        return Inertia::render('Intelligence/GloryLedger/Index', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'timezone' => $alliance->timezone],
            'player' => ['id' => $actor->playerId, 'name' => $actor->currentName],
            'canManage' => $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::ContributionManage),
            'reporting' => $reports->memberDashboard($scope->allianceId, $scope->playerId),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceReferenceQuery $alliances,
        PlayerReferenceQuery $players,
        AllianceIntelligenceAuthorization $authorization,
        ContributionReportingQuery $reports,
    ): Response {
        [$user, $scope, $alliance] = $this->requireManager($request, $context, $alliances, $players, $authorization);

        return Inertia::render('Intelligence/GloryLedger/Manage', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'timezone' => $alliance->timezone],
            'reporting' => $reports->managementDashboard($scope->allianceId),
            'periods' => array_column(ContributionPeriod::cases(), 'value'),
            'dataClasses' => array_column(ContributionDataClass::cases(), 'value'),
            'contributionBulkPreview' => $request->session()->get('contributionBulkPreview'),
            'contributionBulkResult' => $request->session()->get('contributionBulkResult'),
        ]);
    }

    public function storeSelfReport(Request $request, AllianceContext $context, RecordContribution $record): RedirectResponse
    {
        $scope = $context->scope();
        $validated = $request->validate([
            'category_id' => ['required', 'string', 'max:26'],
            'value' => ['required', 'numeric', 'min:0'],
            'evidence' => ['nullable', 'string', 'max:4000'],
        ]);

        $record->handle(
            $scope->playerId,
            $scope->allianceId,
            $scope->playerId,
            (string) $validated['category_id'],
            (float) $validated['value'],
            ContributionRecordSource::SelfReported,
            $validated['evidence'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('contribution-self-report-submitted'));
    }

    public function storeCategory(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, CreateContributionCategory $create): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:4000'],
            'unit' => ['required', 'string', 'max:40'], 'period' => ['required', Rule::enum(ContributionPeriod::class)],
            'period_start' => ['nullable', 'date'], 'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'goal_value' => ['nullable', 'numeric', 'min:0'], 'evidence_required' => ['required', 'boolean'],
            'allow_self_report' => ['required', 'boolean'], 'leaderboard_enabled' => ['required', 'boolean'],
            'data_class' => ['required', Rule::enum(ContributionDataClass::class)], 'calculation_key' => ['nullable', 'string', 'max:80'],
            'calculation_version' => ['nullable', 'string', 'max:40'], 'calculation_description' => ['nullable', 'string', 'max:4000'],
        ]);

        $create->handle(
            $scope->playerId, $scope->allianceId, $validated['name'], $validated['unit'],
            ContributionPeriod::from($validated['period']), ContributionDataClass::from($validated['data_class']),
            isset($validated['goal_value']) ? (float) $validated['goal_value'] : null,
            (bool) $validated['evidence_required'], (bool) $validated['allow_self_report'], (bool) $validated['leaderboard_enabled'],
            $validated['description'] ?? null, $validated['period_start'] ?? null, $validated['period_end'] ?? null,
            $validated['calculation_key'] ?? null, $validated['calculation_version'] ?? null, $validated['calculation_description'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('contribution-category-created'));
    }

    public function storeManualRecord(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, RecordContribution $record): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'ulid'], 'category_id' => ['required', 'string', 'ulid'],
            'value' => ['required', 'numeric', 'min:0'], 'evidence' => ['nullable', 'string', 'max:4000'],
        ]);
        $record->handle($scope->playerId, $scope->allianceId, (string) $validated['player_id'], (string) $validated['category_id'], (float) $validated['value'], ContributionRecordSource::Manual, $validated['evidence'] ?? null);

        return back()->with('actionReceipt', $this->receipt('contribution-recorded'));
    }

    public function approve(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionRecord $record, ApproveContributionRecord $approve): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $approve->handle($scope->playerId, $scope->allianceId, (string) $record->id);

        return back()->with('actionReceipt', $this->receipt('contribution-approved'));
    }

    public function previewBulkApproval(
        Request $request,
        AllianceContext $context,
        PreviewContributionBulkApproval $preview,
    ): RedirectResponse {
        $this->user($request);
        $validated = $this->validateBulkApproval($request);
        $scope = $context->scope();

        /** @var non-empty-list<string> $recordIds */
        $recordIds = array_values($validated['record_ids']);
        $request->session()->flash('contributionBulkPreview', $preview->handle(
            $scope->playerId,
            $scope->allianceId,
            $recordIds,
        ));

        return back();
    }

    public function commitBulkApproval(
        Request $request,
        AllianceContext $context,
        BulkApproveContributionRecords $approve,
    ): RedirectResponse {
        $this->user($request);
        $validated = $this->validateBulkApproval($request);
        $scope = $context->scope();

        /** @var non-empty-list<string> $recordIds */
        $recordIds = array_values($validated['record_ids']);
        $result = $approve->handle($scope->playerId, $scope->allianceId, $recordIds)->toArray();
        $request->session()->flash('contributionBulkResult', $result);

        return back()->with('actionReceipt', $this->receipt('contribution-bulk-approval-completed', [
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function correct(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionRecord $record, CorrectContributionRecord $correct): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $validated = $request->validate(['value' => ['required', 'numeric', 'min:0'], 'reason' => ['required', 'string', 'max:2000'], 'evidence' => ['nullable', 'string', 'max:4000']]);
        $correct->handle($scope->playerId, $scope->allianceId, (string) $record->id, (float) $validated['value'], $validated['reason'], $validated['evidence'] ?? null);

        return back()->with('actionReceipt', $this->receipt('contribution-corrected'));
    }

    public function reverse(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionRecord $record, ReverseContributionRecord $reverse): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $reverse->handle($scope->playerId, $scope->allianceId, (string) $record->id, $validated['reason']);

        return back()->with('actionReceipt', $this->receipt('contribution-reversed'));
    }

    public function refreshQuality(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, RefreshContributionDataQuality $refresh): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $result = $refresh->handle($scope->playerId, $scope->allianceId);

        return back()->with('actionReceipt', $this->receipt('contribution-quality-refreshed', [
            'missingEvidence' => $result['missing_evidence'],
            'missingRecords' => $result['missing_records'],
        ]));
    }

    public function resolveQualityFlag(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionDataQualityFlag $flag, ResolveContributionDataQualityFlag $resolve): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $resolve->handle($scope->playerId, $scope->allianceId, (string) $flag->id);

        return back()->with('actionReceipt', $this->receipt('contribution-quality-flag-resolved'));
    }

    public function storeReportSchedule(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, CreateContributionReportSchedule $create): RedirectResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $validated = $request->validate([
            'recipient_player_id' => ['required', 'string', 'ulid'], 'name' => ['required', 'string', 'max:120'],
            'cadence' => ['required', Rule::in(['daily', 'weekly', 'monthly'])], 'timezone' => ['required', 'timezone'],
            'next_due_at' => ['required', 'date'],
        ]);
        $create->handle($scope->playerId, $scope->allianceId, (string) $validated['recipient_player_id'], $validated['name'], $validated['cadence'], $validated['timezone'], CarbonImmutable::parse($validated['next_due_at'], $validated['timezone']));

        return back()->with('actionReceipt', $this->receipt('contribution-report-schedule-created'));
    }

    public function exportCsv(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionReportExporter $exporter): HttpResponse
    {
        return $this->export($request, $context, $authorization, $exporter, 'csv');
    }

    public function exportSpreadsheet(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionReportExporter $exporter): HttpResponse
    {
        return $this->export($request, $context, $authorization, $exporter, 'spreadsheet');
    }

    private function export(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, ContributionReportExporter $exporter, string $format): HttpResponse
    {
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);
        $result = $exporter->export($scope->allianceId, $scope->playerId, $format);

        return response($result['content'], 200, [
            'Content-Type' => $result['mime'], 'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'X-Report-Version' => $result['reportVersion'], 'X-Report-Checksum' => $result['checksum'],
        ]);
    }

    /** @return array{record_ids: list<string>} */
    private function validateBulkApproval(Request $request): array
    {
        return $request->validate([
            'record_ids' => ['required', 'array', 'min:1', 'max:50'],
            'record_ids.*' => ['required', 'ulid', 'distinct'],
        ]);
    }

    /** @return array{AuthenticatedAccount, AllianceScopeReference, AllianceReference, PlayerReference} */
    private function requireManager(Request $request, AllianceContext $context, AllianceReferenceQuery $alliances, PlayerReferenceQuery $players, AllianceIntelligenceAuthorization $authorization): array
    {
        $user = $this->user($request);
        $scope = $context->scope();
        $this->authorizeManager($scope, $authorization);

        return [$user, $scope, $alliances->require($scope->allianceId), $players->require($scope->playerId)];
    }

    private function authorizeManager(AllianceScopeReference $scope, AllianceIntelligenceAuthorization $authorization): void
    {
        abort_unless($authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::ContributionManage), 403);
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }
}
