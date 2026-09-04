<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedAllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Actions\SaveAllianceRosterEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadAllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidenceReview;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceRosterEvidenceController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        RosterEntryQuery $rosterEntries,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $alliance = $alliances->require($scope->allianceId);

        $evidence = [];
        foreach (AllianceRosterEvidence::query()
            ->where('alliance_id', $scope->allianceId)
            ->latest('created_at')
            ->limit(25)
            ->get() as $item) {
            $review = AllianceRosterEvidenceReview::query()
                ->where('evidence_id', $item->id)
                ->latest('revision_number')
                ->first();

            $reviewProjection = null;
            if ($review instanceof AllianceRosterEvidenceReview) {
                $payloadRows = $review->payload['rows'] ?? [];
                $rows = [];
                if (is_array($payloadRows)) {
                    foreach ($payloadRows as $row) {
                        if (is_array($row)) {
                            $rows[] = $row;
                        }
                    }
                }
                $reviewProjection = [
                    'id' => $review->id,
                    'status' => $review->status->value,
                    'capturedAt' => $review->captured_at->toIso8601String(),
                    'completeRoster' => (bool) ($review->payload['complete_roster'] ?? false),
                    'rows' => $rows,
                ];
            }

            $evidence[] = [
                'id' => $item->id,
                'name' => $item->original_name,
                'status' => $item->lifecycle_status->value,
                'uploadedAt' => $item->created_at?->toIso8601String(),
                'visualDuplicateEvidenceId' => $item->visual_duplicate_evidence_id,
                'review' => $reviewProjection,
            ];
        }

        $roster = array_map(
            static fn (RosterEntryReference $entry): array => [
                'id' => $entry->rosterEntryId,
                'name' => $entry->observedName,
            ],
            array_slice($rosterEntries->all($scope->allianceId), 0, 500),
        );

        return Inertia::render('Alliance/RosterEvidence/Index', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'evidence' => $evidence,
            'rosterEntries' => $roster,
        ]);
    }

    public function upload(Request $request, AllianceContext $context, UploadAllianceRosterEvidence $upload): RedirectResponse
    {
        $validated = $request->validate(['evidence' => ['required', 'file']]);
        $scope = $context->scope();
        $result = $upload->handle($scope->playerId, $scope->allianceId, $validated['evidence']);

        return redirect()->route('alliance.roster.evidence.index')->with(
            'actionReceipt',
            $this->receipt($result->duplicate ? 'alliance-roster-evidence-duplicate' : 'alliance-roster-evidence-uploaded'),
        );
    }

    public function review(
        Request $request,
        AllianceContext $context,
        SaveAllianceRosterEvidenceReview $save,
        string $evidence,
    ): RedirectResponse {
        $validated = $request->validate([
            'captured_at' => ['required', 'date'],
            'complete_roster' => ['required', 'boolean'],
            'allow_semantic_duplicate' => ['sometimes', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:300'],
            'rows.*.observed_name' => ['required', 'string', 'max:160'],
            'rows.*.game_player_id' => ['nullable', 'string', 'max:100'],
            'rows.*.observed_rank' => ['nullable', 'string', 'in:r1,r2,r3,r4,r5'],
            'rows.*.power' => ['nullable', 'integer', 'min:0'],
            'rows.*.roster_entry_id' => ['nullable', 'string', 'ulid'],
        ]);
        $scope = $context->scope();
        $save->handle(
            $scope->playerId,
            $scope->allianceId,
            $evidence,
            (string) $validated['captured_at'],
            array_values((array) $validated['rows']),
            (bool) $validated['complete_roster'],
            (bool) ($validated['allow_semantic_duplicate'] ?? false),
        );

        return redirect()->route('alliance.roster.evidence.index')
            ->with('actionReceipt', $this->receipt('alliance-roster-evidence-reviewed'));
    }

    public function commit(
        AllianceContext $context,
        CommitReviewedAllianceRosterEvidence $commit,
        string $review,
    ): RedirectResponse {
        $scope = $context->scope();
        $receipt = $commit->handle($scope->playerId, $scope->allianceId, $review);

        return redirect()->route('alliance.roster.reconciliation')->with(
            'actionReceipt',
            $this->receipt('alliance-roster-evidence-committed', ['count' => $receipt->rowCount]),
        );
    }
}
