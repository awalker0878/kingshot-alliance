<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\DeleteEvidence;
use App\Contexts\Intelligence\Evidence\Actions\ResolveSemanticDuplicate;
use App\Contexts\Intelligence\Evidence\Actions\RetryEvidenceProcessing;
use App\Contexts\Intelligence\Evidence\Actions\SaveEvidenceReview;
use App\Contexts\Intelligence\Evidence\Actions\UploadGameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\ScreenshotIntake\CommitBearHuntEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EvidenceController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function store(Request $request, string $occurrence, UploadGameEvidence $upload): RedirectResponse
    {
        $request->validate(['evidence' => ['required', 'file']]);
        $file = $request->file('evidence');
        abort_unless($file instanceof UploadedFile, 422);
        $result = $upload->handle($this->actor()->playerId, $occurrence, $file);

        return $this->back($occurrence, [
            'evidenceId' => $result->evidenceId,
            'duplicate' => $result->duplicate ? 1 : 0,
        ]);
    }

    public function image(
        string $occurrence,
        string $evidence,
        BearHuntEvidenceTargetQuery $targets,
    ): StreamedResponse {
        $target = $targets->authorizeManage($this->actor()->playerId, $occurrence);
        $item = GameEvidence::query()
            ->whereKey($evidence)
            ->where('alliance_id', $target->allianceId)
            ->where('occurrence_id', $target->occurrenceId)
            ->firstOrFail();
        abort_if($item->path === null, 410, 'The retained evidence image has been deleted.');
        $disk = Storage::disk((string) $item->disk);
        $stream = $disk->readStream((string) $item->path);
        abort_if($stream === false, 404);

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

    public function review(
        Request $request,
        string $occurrence,
        string $evidence,
        SaveEvidenceReview $save,
    ): RedirectResponse {
        $validated = $request->validate([
            'extraction_attempt_id' => ['required', 'string', 'size:26'],
            'report_timestamp_text' => ['nullable', 'string', 'max:64'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.row_ordinal' => ['required', 'integer', 'min:1', 'max:1000'],
            'rows.*.included' => ['required', 'boolean'],
            'rows.*.player_id' => ['nullable', 'string', 'size:26'],
            'rows.*.player_name' => ['required', 'string', 'max:128'],
            'rows.*.reported_rank' => ['nullable', 'integer', 'min:1', 'max:999'],
            'rows.*.damage_points' => ['nullable', 'integer', 'min:0'],
            'rows.*.correction_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $reviewId = $save->handle(
            actorPlayerId: $this->actor()->playerId,
            evidenceId: $evidence,
            extractionAttemptId: (string) $validated['extraction_attempt_id'],
            rows: $validated['rows'],
            reportTimestampText: $validated['report_timestamp_text'] ?? null,
        );

        return $this->back($occurrence, ['reviewId' => $reviewId]);
    }

    public function resolveDuplicate(
        Request $request,
        string $occurrence,
        string $review,
        ResolveSemanticDuplicate $resolve,
    ): RedirectResponse {
        $validated = $request->validate([
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $resolve->handle($this->actor()->playerId, $review, (string) $validated['justification']);

        return $this->back($occurrence, ['reviewId' => $review]);
    }

    public function retry(
        string $occurrence,
        string $evidence,
        RetryEvidenceProcessing $retry,
    ): RedirectResponse {
        $retry->handle($this->actor()->playerId, $occurrence, $evidence);

        return $this->back($occurrence, ['evidenceId' => $evidence]);
    }

    public function commit(
        string $occurrence,
        string $review,
        CommitBearHuntEvidence $commit,
    ): RedirectResponse {
        $receipt = $commit->handle($this->actor()->playerId, $review);

        return $this->back($occurrence, [
            'reviewId' => $review,
            'reportId' => $receipt->reportId,
            'entryCount' => $receipt->entryCount,
            'replayed' => $receipt->replayed ? 1 : 0,
        ]);
    }

    public function destroy(
        string $occurrence,
        string $evidence,
        DeleteEvidence $delete,
    ): RedirectResponse {
        $delete->handle($this->actor()->playerId, $occurrence, $evidence);

        return $this->back($occurrence, ['evidenceId' => $evidence]);
    }

    /** @param array<string, int|string> $parameters */
    private function back(string $occurrence, array $parameters): RedirectResponse
    {
        return redirect()->route('events.screenshot-intake', ['occurrence' => $occurrence])
            ->with('actionReceipt', $this->receipt('completed', $parameters));
    }

    private function actor(): PlayerReference
    {
        $actor = $this->playerContext->playerOrNull();
        abort_unless($actor instanceof PlayerReference, 409, 'Select a Player before importing battle reports.');

        return $actor;
    }
}
