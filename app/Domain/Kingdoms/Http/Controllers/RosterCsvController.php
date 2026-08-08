<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CommitRosterCsvImport;
use App\Domain\Kingdoms\Actions\PreviewRosterCsvImport;
use App\Domain\Kingdoms\Models\RosterImport;
use App\Domain\Kingdoms\Services\RosterCsvExporter;
use App\Domain\Kingdoms\Services\RosterCsvParser;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RosterCsvController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        $this->authorizeManage($authorization, $user, $alliance);

        return $this->page($alliance, null);
    }

    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        string $import,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        $this->authorizeManage($authorization, $user, $alliance);

        $record = RosterImport::query()
            ->where('alliance_id', $alliance->id)
            ->findOrFail($import);

        return $this->page($alliance, $record);
    }

    public function preview(
        Request $request,
        AllianceContext $context,
        PreviewRosterCsvImport $preview,
    ): RedirectResponse {
        $request->validate([
            'file' => ['required', 'file', 'max:1024'],
        ]);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $import = $preview->handle($context->alliance(), $this->user($request), $file);

        return to_route('alliance.roster.import.show', ['import' => $import->id])
            ->with('status', $import->status === RosterImport::STATUS_COMMITTED
                ? 'roster-import-already-committed'
                : 'roster-import-previewed');
    }

    public function commit(
        Request $request,
        AllianceContext $context,
        CommitRosterCsvImport $commit,
        string $import,
    ): RedirectResponse {
        /** @var array{resolutions?: array<int|string, string>} $validated */
        $validated = $request->validate([
            'resolutions' => ['nullable', 'array'],
            'resolutions.*' => ['string', 'max:64'],
        ]);

        $record = $commit->handle(
            $context->alliance(),
            $this->user($request),
            $import,
            $validated['resolutions'] ?? [],
        );

        return to_route('alliance.roster.import.show', ['import' => $record->id])
            ->with('status', 'roster-import-committed');
    }

    public function export(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RosterCsvExporter $exporter,
    ): HttpResponse {
        /** @var array{scope?: string} $validated */
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['member', 'management'])],
        ]);

        $user = $this->user($request);
        $alliance = $context->alliance();
        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $permission = $includePrivate ? PermissionKey::KingdomManage : PermissionKey::AllianceView;

        if (! $authorization->allows($user, $alliance, $permission)) {
            throw new AuthorizationException;
        }

        $export = $exporter->export($alliance, $user, $includePrivate);
        $filename = str_replace(['"', "\r", "\n"], '', $export['filename']);

        return response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function page(\App\Domain\Alliances\Models\Alliance $alliance, ?RosterImport $import): Response
    {
        return Inertia::render('Alliance/RosterImport', [
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
            ],
            'schema' => [
                'version' => RosterCsvParser::SCHEMA_VERSION,
                'headers' => RosterCsvParser::HEADERS,
                'maxBytes' => RosterCsvParser::MAX_BYTES,
                'maxRows' => RosterCsvParser::MAX_ROWS,
            ],
            'import' => $import === null ? null : [
                'id' => (string) $import->id,
                'status' => (string) $import->status,
                'filename' => (string) $import->original_filename,
                'checksum' => (string) $import->checksum,
                'rowCount' => (int) $import->row_count,
                'createCount' => (int) $import->create_count,
                'updateCount' => (int) $import->update_count,
                'ambiguousCount' => (int) $import->ambiguous_count,
                'rejectedCount' => (int) $import->rejected_count,
                'rows' => is_array($import->preview_payload['rows'] ?? null)
                    ? $import->preview_payload['rows']
                    : [],
                'resolutions' => $import->resolution_payload ?? [],
                'committedSummary' => $import->committed_summary,
                'committedAt' => $import->committed_at?->toIso8601String(),
            ],
        ]);
    }

    private function authorizeManage(
        AllianceAuthorization $authorization,
        User $user,
        \App\Domain\Alliances\Models\Alliance $alliance,
    ): void {
        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
