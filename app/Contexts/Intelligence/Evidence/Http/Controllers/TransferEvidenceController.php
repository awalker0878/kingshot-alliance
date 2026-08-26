<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEvidencePreviewKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidencePreviewQuery;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEvidencePreviewInput;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedTransferEvidence;
use App\Contexts\Intelligence\Evidence\Actions\DeleteTransferEvidence;
use App\Contexts\Intelligence\Evidence\Actions\ResolveTransferSemanticDuplicate;
use App\Contexts\Intelligence\Evidence\Actions\RetryTransferEvidenceProcessing;
use App\Contexts\Intelligence\Evidence\Actions\SaveTransferEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadTransferEvidence;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReviewKingdom;
use App\Contexts\Intelligence\Evidence\Queries\TransferEvidenceSummaryQuery;
use App\Contexts\Intelligence\Evidence\Services\TransferEvidenceSchemaRegistry;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TransferEvidenceController extends Controller
{
    public function index(
        AllianceContext $context,
        TransferEvidenceTargetQuery $targets,
        TransferEvidenceSummaryQuery $summaries,
        TransferEvidenceSchemaRegistry $schemas,
        string $plan,
        string $participant,
    ): JsonResponse {
        $scope = $context->scope();
        $targets->authorizeManage($scope->playerId, $scope->allianceId, $plan, $participant);
        $byParticipant = $summaries->forPlan($scope->allianceId, $plan);

        return response()->json([
            'evidence' => $byParticipant[$participant] ?? [],
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
            }, EvidenceKind::transferCases()),
        ]);
    }

    public function image(
        AllianceContext $context,
        TransferEvidenceTargetQuery $targets,
        string $plan,
        string $participant,
        string $evidence,
    ): StreamedResponse {
        $scope = $context->scope();
        $targets->authorizeManage($scope->playerId, $scope->allianceId, $plan, $participant);
        $item = GameEvidence::query()
            ->whereKey($evidence)
            ->where('alliance_id', $scope->allianceId)
            ->whereNull('occurrence_id')
            ->where('transfer_plan_id', $plan)
            ->where('transfer_participant_id', $participant)
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
        AllianceContext $context,
        TransferEvidenceTargetQuery $targets,
        TransferEvidencePreviewQuery $preview,
        string $plan,
        string $participant,
        string $review,
    ): JsonResponse {
        $scope = $context->scope();
        $targets->authorizeManage($scope->playerId, $scope->allianceId, $plan, $participant);
        $item = TransferEvidenceReview::query()
            ->whereKey($review)
            ->where('alliance_id', $scope->allianceId)
            ->where('transfer_plan_id', $plan)
            ->where('transfer_participant_id', $participant)
            ->firstOrFail();
        /** @var list<int> $kingdomNumbers */
        $kingdomNumbers = TransferEvidenceReviewKingdom::query()
            ->where('review_id', $item->id)
            ->orderBy('ordinal')
            ->pluck('kingdom_number')
            ->map(static fn ($number): int => (int) $number)
            ->values()
            ->all();
        $result = $preview->preview(
            $scope->playerId,
            $scope->allianceId,
            $plan,
            $participant,
            new TransferEvidencePreviewInput(
                kind: TransferEvidencePreviewKind::from($item->evidence_kind->value),
                observedAt: $item->observed_at->toIso8601String(),
                validUntil: $item->valid_until?->toIso8601String(),
                governorPower: $item->governor_power,
                transferScore: $item->transfer_score,
                passesAvailable: $item->transfer_passes_available,
                passesRequired: $item->transfer_passes_required,
                invitationStatus: $item->invitation_status,
                targetPowerCap: $item->target_power_cap,
                kingdomClassification: $item->kingdom_classification,
                officialGroupIdentifier: $item->official_group_identifier,
                officialGroupKingdomNumbers: $kingdomNumbers,
            ),
        );

        return response()->json($result);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        UploadTransferEvidence $upload,
        string $plan,
        string $participant,
    ): RedirectResponse {
        $validated = $request->validate([
            'evidence_kind' => ['required', Rule::in(array_map(
                static fn (EvidenceKind $kind): string => $kind->value,
                EvidenceKind::transferCases(),
            ))],
            'evidence' => ['required', 'file'],
        ]);
        $file = $request->file('evidence');
        abort_unless($file instanceof UploadedFile, 422);
        $scope = $context->scope();
        $result = $upload->handle(
            $scope->playerId,
            $scope->allianceId,
            $plan,
            $participant,
            EvidenceKind::from((string) $validated['evidence_kind']),
            $file,
        );

        return $this->back([
            'evidenceId' => $result->evidenceId,
            'duplicate' => $result->duplicate ? 1 : 0,
        ]);
    }

    public function review(
        Request $request,
        AllianceContext $context,
        SaveTransferEvidenceReview $save,
        string $plan,
        string $participant,
        string $evidence,
    ): RedirectResponse {
        $validated = $request->validate([
            'extraction_attempt_id' => ['required', 'string', 'size:26'],
            'observed_at' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'governor_power' => ['nullable', 'integer', 'min:0'],
            'transfer_score' => ['nullable', 'integer', 'min:0'],
            'transfer_passes_available' => ['nullable', 'integer', 'min:0'],
            'transfer_passes_required' => ['nullable', 'integer', 'min:0'],
            'invitation_status' => ['nullable', Rule::in(array_column(TransferInvitationStatus::cases(), 'value'))],
            'target_kingdom_number' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'target_power_cap' => ['nullable', 'integer', 'min:0'],
            'kingdom_classification' => ['nullable', Rule::in(array_column(TransferKingdomClassification::cases(), 'value'))],
            'official_group_identifier' => ['nullable', 'string', 'max:96'],
            'kingdom_numbers' => ['nullable', 'array', 'max:250'],
            'kingdom_numbers.*' => ['integer', 'min:1', 'max:999999'],
        ]);
        $scope = $context->scope();
        $reviewId = $save->handle(
            actorPlayerId: $scope->playerId,
            allianceId: $scope->allianceId,
            planId: $plan,
            participantId: $participant,
            evidenceId: $evidence,
            extractionAttemptId: (string) $validated['extraction_attempt_id'],
            observedAt: (string) $validated['observed_at'],
            validUntil: isset($validated['valid_until']) ? (string) $validated['valid_until'] : null,
            governorPower: isset($validated['governor_power']) ? (int) $validated['governor_power'] : null,
            transferScore: isset($validated['transfer_score']) ? (int) $validated['transfer_score'] : null,
            transferPassesAvailable: isset($validated['transfer_passes_available']) ? (int) $validated['transfer_passes_available'] : null,
            transferPassesRequired: isset($validated['transfer_passes_required']) ? (int) $validated['transfer_passes_required'] : null,
            invitationStatus: isset($validated['invitation_status']) ? (string) $validated['invitation_status'] : null,
            targetKingdomNumber: isset($validated['target_kingdom_number']) ? (int) $validated['target_kingdom_number'] : null,
            targetPowerCap: isset($validated['target_power_cap']) ? (int) $validated['target_power_cap'] : null,
            kingdomClassification: isset($validated['kingdom_classification']) ? (string) $validated['kingdom_classification'] : null,
            officialGroupIdentifier: isset($validated['official_group_identifier']) ? (string) $validated['official_group_identifier'] : null,
            officialGroupKingdomNumbers: array_values(array_map('intval', $validated['kingdom_numbers'] ?? [])),
        );

        return $this->back(['evidenceId' => $evidence, 'reviewId' => $reviewId]);
    }

    public function resolveDuplicate(
        Request $request,
        AllianceContext $context,
        ResolveTransferSemanticDuplicate $resolve,
        string $plan,
        string $participant,
        string $review,
    ): RedirectResponse {
        $validated = $request->validate([
            'justification' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $scope = $context->scope();
        $resolve->handle(
            $scope->playerId,
            $scope->allianceId,
            $plan,
            $participant,
            $review,
            (string) $validated['justification'],
        );

        return $this->back(['reviewId' => $review]);
    }

    public function commit(
        AllianceContext $context,
        CommitReviewedTransferEvidence $commit,
        string $plan,
        string $participant,
        string $review,
    ): RedirectResponse {
        $scope = $context->scope();
        $receipt = $commit->handle(
            $scope->playerId,
            $scope->allianceId,
            $plan,
            $participant,
            $review,
        );

        return $this->back([
            'reviewId' => $review,
            'destinationReceiptId' => $receipt->receiptId,
            'destinationCount' => count($receipt->destinationIds),
            'replayed' => $receipt->idempotentReplay ? 1 : 0,
        ]);
    }

    public function retry(
        AllianceContext $context,
        RetryTransferEvidenceProcessing $retry,
        string $plan,
        string $participant,
        string $evidence,
    ): RedirectResponse {
        $scope = $context->scope();
        $retry->handle($scope->playerId, $scope->allianceId, $plan, $participant, $evidence);

        return $this->back(['evidenceId' => $evidence]);
    }

    public function destroy(
        AllianceContext $context,
        DeleteTransferEvidence $delete,
        string $plan,
        string $participant,
        string $evidence,
    ): RedirectResponse {
        $scope = $context->scope();
        $delete->handle($scope->playerId, $scope->allianceId, $plan, $participant, $evidence);

        return $this->back(['evidenceId' => $evidence]);
    }

    /** @param array<string,int|string> $parameters */
    private function back(array $parameters): RedirectResponse
    {
        return redirect()->route('alliance.transfers.readiness')
            ->with('actionReceipt', $this->receipt('transfer-evidence-updated', $parameters));
    }
}
