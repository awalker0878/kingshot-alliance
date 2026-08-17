<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Contexts\Intelligence\Roster\Services\RosterCsvParser;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RosterImportReadController extends Controller
{
    public function index(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, AccountIdentityQuery $accounts): Response
    {
        return $this->page($request, $context, $authorization, $alliances, $kingdoms, $accounts, null);
    }

    public function show(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, AccountIdentityQuery $accounts, string $import): Response
    {
        $scope = $context->scope();
        $record = RosterImport::query()->where('alliance_id', $scope->allianceId)->findOrFail($import);

        return $this->page($request, $context, $authorization, $alliances, $kingdoms, $accounts, $record);
    }

    private function page(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, AccountIdentityQuery $accounts, ?RosterImport $import): Response
    {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);

        return Inertia::render('Intelligence/Roster/Import', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name, 'kingdom' => (string) $kingdom->number],
            'schema' => [
                'version' => RosterCsvParser::SCHEMA_VERSION,
                'headers' => RosterCsvParser::HEADERS,
                'maxBytes' => RosterCsvParser::MAX_BYTES,
                'maxRows' => RosterCsvParser::MAX_ROWS,
            ],
            'importRecord' => $import === null ? null : [
                'id' => (string) $import->id,
                'status' => (string) $import->status,
                'filename' => (string) $import->original_filename,
                'checksum' => (string) $import->checksum,
                'rowCount' => (int) $import->row_count,
                'createCount' => (int) $import->create_count,
                'updateCount' => (int) $import->update_count,
                'ambiguousCount' => (int) $import->ambiguous_count,
                'rejectedCount' => (int) $import->rejected_count,
                'rows' => is_array($import->preview_payload['rows'] ?? null) ? $import->preview_payload['rows'] : [],
                'resolutions' => $import->resolution_payload ?? [],
                'committedSummary' => $import->committed_summary,
                'committedAt' => $import->committed_at?->toIso8601String(),
            ],
        ]);
    }
}
