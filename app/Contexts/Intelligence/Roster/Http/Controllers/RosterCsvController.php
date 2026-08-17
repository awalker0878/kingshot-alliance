<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Actions\CommitRosterCsvImport;
use App\Contexts\Intelligence\Roster\Actions\PreviewRosterCsvImport;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Contexts\Intelligence\Roster\Services\RosterCsvExporter;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class RosterCsvController extends Controller
{
    public function preview(Request $request, AllianceContext $context, PreviewRosterCsvImport $preview): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:1024']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $scope = $context->scope();
        $import = $preview->handle($scope->allianceId, $scope->playerId, $file);

        return to_route('alliance.roster.import.show', ['import' => $import['importId']])
            ->with('status', $import['status'] === RosterImport::STATUS_COMMITTED ? 'roster-import-already-committed' : 'roster-import-previewed');
    }

    public function commit(Request $request, AllianceContext $context, CommitRosterCsvImport $commit, string $import): RedirectResponse
    {
        /** @var array{resolutions?:array<int|string,string>} $validated */
        $validated = $request->validate([
            'resolutions' => ['nullable', 'array'],
            'resolutions.*' => ['string', 'max:64'],
        ]);
        $scope = $context->scope();
        $importId = $commit->handle($scope->allianceId, $scope->playerId, $import, $validated['resolutions'] ?? []);

        return to_route('alliance.roster.import.show', ['import' => $importId])->with('status', 'roster-import-committed');
    }

    public function export(Request $request, AllianceContext $context, AllianceIntelligenceAuthorization $authorization, RosterCsvExporter $exporter): HttpResponse
    {
        /** @var array{scope?:string} $validated */
        $validated = $request->validate(['scope' => ['nullable', Rule::in(['member', 'management'])]]);
        $scope = $context->scope();
        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $permission = $includePrivate ? IntelligencePermission::KingdomManage : IntelligencePermission::View;
        if (! $authorization->allows($scope->playerId, $scope->allianceId, $permission)) {
            throw new AuthorizationException;
        }
        $export = $exporter->export($scope->allianceId, $scope->playerId, $includePrivate);
        $filename = str_replace(['"', "\r", "\n"], '', $export['filename']);

        return response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
