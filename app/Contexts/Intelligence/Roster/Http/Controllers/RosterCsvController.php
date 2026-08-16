<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Roster\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Roster\Actions\CommitRosterCsvImport;
use App\Contexts\Intelligence\Roster\Actions\PreviewRosterCsvImport;
use App\Contexts\Intelligence\Roster\Models\RosterImport;
use App\Contexts\Intelligence\Roster\Services\RosterCsvExporter;
use App\Contexts\Intelligence\Roster\Services\RosterCsvParser;
use App\Shared\Http\Controller;
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
        AllianceIntelligenceAuthorization $authorization,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        $this->authorizeManage($authorization, $context->player(), $alliance);

        return $this->page($alliance, $user, null);
    }

    public function show(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        string $import,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');
        $this->authorizeManage($authorization, $context->player(), $alliance);

        $record = RosterImport::query()
            ->where('alliance_id', $alliance->id)
            ->findOrFail($import);

        return $this->page($alliance, $user, $record);
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

        $import = $preview->handle($context->alliance(), $context->player(), $file);

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
            $context->player(),
            $import,
            $validated['resolutions'] ?? [],
        );

        return to_route('alliance.roster.import.show', ['import' => $record->id])
            ->with('status', 'roster-import-committed');
    }

    public function export(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $allianceAuthorization,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        RosterCsvExporter $exporter,
    ): HttpResponse {
        /** @var array{scope?: string} $validated */
        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['member', 'management'])],
        ]);

        $user = $this->user($request);
        $alliance = $context->alliance();
        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $allowed = $includePrivate
            ? $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)
            : $allianceAuthorization->allows($context->player(), $alliance, AlliancePermission::View);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $export = $exporter->export($alliance, $context->player(), $includePrivate);
        $filename = str_replace(['"', "\r", "\n"], '', $export['filename']);

        return response($export['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function page(Alliance $alliance, User $user, ?RosterImport $import): Response
    {
        return Inertia::render('Alliance/RosterImport', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
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
        AllianceIntelligenceAuthorization $authorization,
        Player $actor,
        Alliance $alliance,
    ): void {
        if (! $authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
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
