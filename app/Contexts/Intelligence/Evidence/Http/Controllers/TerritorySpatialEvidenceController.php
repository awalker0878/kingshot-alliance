<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Http\Controllers;

use App\Contexts\Alliance\Membership\Queries\ActiveAllianceScopeQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Actions\CommitReviewedSpatialEvidence;
use App\Contexts\Intelligence\Evidence\Actions\DeleteTerritorySpatialEvidence;
use App\Contexts\Intelligence\Evidence\Actions\ResolveSpatialSemanticDuplicate;
use App\Contexts\Intelligence\Evidence\Actions\RetryTerritorySpatialEvidenceProcessing;
use App\Contexts\Intelligence\Evidence\Actions\SaveSpatialEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadTerritorySpatialEvidence;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Observations\Actions\InvalidateSpatialObservation;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TerritorySpatialEvidenceController extends Controller
{
    public function __construct(private readonly ActiveAllianceScopeQuery $allianceScopes) {}

    public function image(
        PlayerContext $context,
        AllianceIntelligenceAuthorization $authorization,
        string $evidence,
    ): StreamedResponse {
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $this->authorizeManage($authorization, $actorPlayerId, $allianceId);
        $item = GameEvidence::query()
            ->whereKey($evidence)
            ->where('alliance_id', $allianceId)
            ->where('kingdom_id', $kingdomId)
            ->where('expected_kind', EvidenceKind::TerritoryMapObservation->value)
            ->firstOrFail();
        abort_if($item->path === null, 410, 'The retained Territory evidence image has been deleted.');
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

    public function store(
        Request $request,
        PlayerContext $context,
        UploadTerritorySpatialEvidence $upload,
    ): RedirectResponse {
        $validated = $request->validate([
            'kingdom_id' => ['required', 'ulid'],
            'map_dataset_id' => ['required', 'string', 'max:120'],
            'map_dataset_checksum' => ['required', 'string', 'size:64'],
            'evidence' => ['required', 'file'],
        ]);
        $file = $request->file('evidence');
        abort_unless($file instanceof UploadedFile, 422);
        [$actorPlayerId, $allianceId] = $this->scope($context);
        $result = $upload->handle(
            actorPlayerId: $actorPlayerId,
            allianceId: $allianceId,
            kingdomId: (string) $validated['kingdom_id'],
            mapDatasetId: (string) $validated['map_dataset_id'],
            mapDatasetChecksum: (string) $validated['map_dataset_checksum'],
            file: $file,
        );

        return back()->with('actionReceipt', $this->receipt('completed', [
            'evidenceId' => $result->evidenceId,
            'duplicate' => $result->duplicate ? 1 : 0,
        ]));
    }

    public function review(
        Request $request,
        PlayerContext $context,
        SaveSpatialEvidenceReview $save,
        string $evidence,
    ): RedirectResponse {
        $validated = $request->validate([
            'captured_at' => ['required', 'date'],
            'coverage_kind' => ['required', Rule::enum(SpatialObservationCoverageKind::class)],
            'completeness' => ['required', Rule::enum(SpatialObservationCompleteness::class)],
            'coverage_bounds' => ['nullable', 'array:x,y,width,height'],
            'coverage_bounds.x' => ['required_with:coverage_bounds', 'integer'],
            'coverage_bounds.y' => ['required_with:coverage_bounds', 'integer'],
            'coverage_bounds.width' => ['required_with:coverage_bounds', 'integer', 'min:1'],
            'coverage_bounds.height' => ['required_with:coverage_bounds', 'integer', 'min:1'],
            'objects' => ['required', 'array', 'max:5000'],
            'objects.*.key' => ['required', 'string', 'max:120'],
            'objects.*.type' => ['required', Rule::enum(SpatialObservedObjectType::class)],
            'objects.*.x' => ['required', 'integer'],
            'objects.*.y' => ['required', 'integer'],
            'objects.*.identity_state' => ['required', Rule::enum(SpatialObservedIdentityState::class)],
            'objects.*.player_id' => ['nullable', 'ulid'],
            'objects.*.plan_local_identity' => ['nullable', 'string', 'max:191'],
            'objects.*.observed_label' => ['nullable', 'string', 'max:191'],
            'objects.*.confidence' => ['nullable', 'numeric', 'between:0,1'],
            'objects.*.source_metadata' => ['nullable', 'array'],
        ]);
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $reviewId = $save->handle(
            actorPlayerId: $actorPlayerId,
            allianceId: $allianceId,
            kingdomId: $kingdomId,
            evidenceId: $evidence,
            capturedAt: (string) $validated['captured_at'],
            coverageKind: SpatialObservationCoverageKind::from((string) $validated['coverage_kind']),
            completeness: SpatialObservationCompleteness::from((string) $validated['completeness']),
            coverageBounds: is_array($validated['coverage_bounds'] ?? null)
                ? $validated['coverage_bounds']
                : null,
            objects: $validated['objects'],
        );

        return back()->with('actionReceipt', $this->receipt('completed', [
            'evidenceId' => $evidence,
            'reviewId' => $reviewId,
        ]));
    }

    public function resolveDuplicate(
        Request $request,
        PlayerContext $context,
        ResolveSpatialSemanticDuplicate $resolve,
        string $review,
    ): RedirectResponse {
        $validated = $request->validate([
            'justification' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $resolve->handle(
            $actorPlayerId,
            $allianceId,
            $kingdomId,
            $review,
            (string) $validated['justification'],
        );

        return back()->with('actionReceipt', $this->receipt('completed', ['reviewId' => $review]));
    }

    public function commit(
        PlayerContext $context,
        CommitReviewedSpatialEvidence $commit,
        string $review,
    ): RedirectResponse {
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $receipt = $commit->handle($actorPlayerId, $allianceId, $kingdomId, $review);

        return back()->with('actionReceipt', $this->receipt('completed', [
            'reviewId' => $review,
            'destinationReceiptId' => $receipt->receiptId,
            'destinationObservationId' => $receipt->observationId,
            'replayed' => $receipt->idempotentReplay ? 1 : 0,
        ]));
    }

    public function retry(
        PlayerContext $context,
        RetryTerritorySpatialEvidenceProcessing $retry,
        string $evidence,
    ): RedirectResponse {
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $retry->handle($actorPlayerId, $allianceId, $kingdomId, $evidence);

        return back()->with('actionReceipt', $this->receipt('completed', ['evidenceId' => $evidence]));
    }

    public function destroy(
        Request $request,
        PlayerContext $context,
        DeleteTerritorySpatialEvidence $delete,
        string $evidence,
    ): RedirectResponse {
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $delete->handle(
            $actorPlayerId,
            $allianceId,
            $kingdomId,
            $evidence,
            (string) $request->input('reason', 'user_requested'),
        );

        return back()->with('actionReceipt', $this->receipt('completed', ['evidenceId' => $evidence]));
    }

    public function invalidate(
        Request $request,
        PlayerContext $context,
        InvalidateSpatialObservation $invalidate,
        string $observation,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        [$actorPlayerId, $allianceId, $kingdomId] = $this->scope($context);
        $invalidate->handle(
            $actorPlayerId,
            $allianceId,
            $kingdomId,
            $observation,
            (string) $validated['reason'],
        );

        return back()->with('actionReceipt', $this->receipt('completed', [
            'observationId' => $observation,
        ]));
    }

    /** @return array{0:string,1:string,2:string} */
    private function scope(PlayerContext $context): array
    {
        $player = $context->player();
        $scope = $this->allianceScopes->findForPlayer($player->playerId, $player->kingdomId);
        abort_if($scope === null, 403, 'An active Alliance membership is required.');

        return [$player->playerId, $scope->allianceId, $scope->kingdomId];
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
}
