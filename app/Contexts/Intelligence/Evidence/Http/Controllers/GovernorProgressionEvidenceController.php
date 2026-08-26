<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Http\Controllers;

use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Actions\DeleteGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Actions\ResolveGovernorProgressionSemanticDuplicate;
use App\Contexts\Intelligence\Evidence\Actions\RetryGovernorProgressionEvidenceProcessing;
use App\Contexts\Intelligence\Evidence\Actions\SaveGovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadGovernorProgressionEvidence;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Queries\GovernorProgressionEvidenceSummaryQuery;
use App\Contexts\Intelligence\Evidence\Services\GovernorProgressionEvidenceSchemaRegistry;
use App\Contexts\Intelligence\Roster\Queries\GovernorProgressionObservationQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GovernorProgressionEvidenceController extends Controller
{
    public function index(
        PlayerContext $context,
        AllianceIntelligenceAuthorization $authorization,
        RosterEntryQuery $roster,
        GovernorProgressionEvidenceSummaryQuery $summaries,
        GovernorProgressionEvidenceSchemaRegistry $schemas,
        string $entry,
    ): JsonResponse {
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $this->authorizeManage($authorization, $actorPlayerId, $allianceId);
        $target = $roster->requireActiveOrTracked($allianceId, $entry);

        return response()->json([
            'target' => [
                'rosterEntryId' => $target->rosterEntryId,
                'playerId' => $target->playerId,
                'name' => $target->observedName,
            ],
            'evidence' => $summaries->forRosterEntry($allianceId, $entry),
            'schemas' => array_map(static function (EvidenceKind $kind) use ($schemas): array {
                $schema = $schemas->require($kind);

                return [
                    'kind' => $kind->value,
                    'version' => $schema->version,
                    'supportedFields' => $schema->supportedFields,
                    'requiredFields' => $schema->requiredFields,
                    'minimumClassificationConfidence' => $schema->minimumClassificationConfidence,
                    'minimumFieldConfidence' => $schema->minimumFieldConfidence,
                    'fixtureCorpus' => $schema->fixtureCorpus,
                    'destinationAction' => $schema->destinationAction,
                ];
            }, EvidenceKind::governorProgressionCases()),
        ]);
    }

    public function image(
        PlayerContext $context,
        AllianceIntelligenceAuthorization $authorization,
        RosterEntryQuery $roster,
        string $entry,
        string $evidence,
    ): StreamedResponse {
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $this->authorizeManage($authorization, $actorPlayerId, $allianceId);
        $roster->requireActiveOrTracked($allianceId, $entry);
        $item = GameEvidence::query()
            ->whereKey($evidence)
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $entry)
            ->whereNull('occurrence_id')
            ->whereNull('transfer_plan_id')
            ->whereNull('transfer_participant_id')
            ->firstOrFail();
        abort_if($item->path === null, 410, 'The retained evidence image has been deleted.');
        $disk = Storage::disk((string) $item->disk);
        $stream = $disk->readStream((string) $item->path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => (string) $item->mime_type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(
        PlayerContext $context,
        AllianceIntelligenceAuthorization $authorization,
        RosterEntryQuery $roster,
        GovernorProgressionObservationQuery $observations,
        string $entry,
        string $review,
    ): JsonResponse {
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $this->authorizeManage($authorization, $actorPlayerId, $allianceId);
        $target = $roster->requireActiveOrTracked($allianceId, $entry);
        $item = GovernorProgressionEvidenceReview::query()
            ->whereKey($review)
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $entry)
            ->where('player_id', $target->playerId)
            ->firstOrFail();

        return response()->json($observations->preview(
            allianceId: $allianceId,
            rosterEntryId: $entry,
            kind: $item->evidence_kind,
            payload: is_array($item->payload) ? $item->payload : [],
            capturedAt: $item->captured_at->toIso8601String(),
            progressionDatasetId: (string) $item->progression_dataset_id,
            progressionDatasetChecksum: (string) $item->progression_dataset_checksum,
            evidenceId: (string) $item->evidence_id,
            reviewId: (string) $item->id,
        ));
    }

    public function store(
        Request $request,
        PlayerContext $context,
        UploadGovernorProgressionEvidence $upload,
        string $entry,
    ): RedirectResponse {
        $validated = $request->validate([
            'evidence_kind' => ['required', Rule::in(array_map(static fn (EvidenceKind $kind): string => $kind->value, EvidenceKind::governorProgressionCases()))],
            'evidence' => ['required', 'file'],
        ]);
        $file = $request->file('evidence');
        abort_unless($file instanceof UploadedFile, 422);
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $result = $upload->handle(
            $actorPlayerId,
            $allianceId,
            $entry,
            EvidenceKind::from((string) $validated['evidence_kind']),
            $file,
        );

        return $this->back(['evidenceId' => $result->evidenceId, 'duplicate' => $result->duplicate ? 1 : 0]);
    }

    public function review(
        Request $request,
        PlayerContext $context,
        SaveGovernorProgressionEvidenceReview $save,
        string $entry,
        string $evidence,
    ): RedirectResponse {
        $validated = $request->validate([
            'normalization_attempt_id' => ['required', 'string', 'size:26'],
            'captured_at' => ['required', 'date'],
            'payload' => ['required', 'array'],
        ]);
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $reviewId = $save->handle(
            actorPlayerId: $actorPlayerId,
            allianceId: $allianceId,
            rosterEntryId: $entry,
            evidenceId: $evidence,
            normalizationAttemptId: (string) $validated['normalization_attempt_id'],
            capturedAt: (string) $validated['captured_at'],
            payload: $validated['payload'],
        );

        return $this->back(['evidenceId' => $evidence, 'reviewId' => $reviewId]);
    }

    public function resolveDuplicate(
        Request $request,
        PlayerContext $context,
        ResolveGovernorProgressionSemanticDuplicate $resolve,
        string $entry,
        string $review,
    ): RedirectResponse {
        $validated = $request->validate(['justification' => ['required', 'string', 'min:8', 'max:1000']]);
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $resolve->handle($actorPlayerId, $allianceId, $entry, $review, (string) $validated['justification']);

        return $this->back(['reviewId' => $review]);
    }

    public function commit(
        PlayerContext $context,
        CommitReviewedGovernorProgressionEvidence $commit,
        string $entry,
        string $review,
    ): RedirectResponse {
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $receipt = $commit->handle($actorPlayerId, $allianceId, $entry, $review);

        return $this->back([
            'reviewId' => $review,
            'destinationReceiptId' => $receipt->receiptId,
            'destinationObservationId' => $receipt->observationId,
            'replayed' => $receipt->idempotentReplay ? 1 : 0,
        ]);
    }

    public function retry(
        PlayerContext $context,
        RetryGovernorProgressionEvidenceProcessing $retry,
        string $entry,
        string $evidence,
    ): RedirectResponse {
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $retry->handle($actorPlayerId, $allianceId, $entry, $evidence);

        return $this->back(['evidenceId' => $evidence]);
    }

    public function destroy(
        PlayerContext $context,
        DeleteGovernorProgressionEvidence $delete,
        string $entry,
        string $evidence,
    ): RedirectResponse {
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $delete->handle($actorPlayerId, $allianceId, $entry, $evidence);

        return $this->back(['evidenceId' => $evidence]);
    }

    /** @return array{0:string,1:string} */
    private function scope(PlayerContext $context): array
    {
        $player = $context->player();
        $membership = AllianceMembership::query()
            ->where('player_id', $player->playerId)
            ->where('status', MembershipStatus::Active->value)
            ->first();
        abort_unless($membership instanceof AllianceMembership, 403, 'An active Alliance membership is required.');

        return [$player->playerId, (string) $membership->alliance_id];
    }

    private function authorizeManage(
        AllianceIntelligenceAuthorization $authorization,
        string $actorPlayerId,
        string $allianceId,
    ): void {
        if (! $authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
    }

    /** @param array<string,int|string> $parameters */
    private function back(array $parameters): RedirectResponse
    {
        return redirect()->route('progression.governor')
            ->with('actionReceipt', $this->receipt('governor-progression-evidence-updated', $parameters));
    }
}
