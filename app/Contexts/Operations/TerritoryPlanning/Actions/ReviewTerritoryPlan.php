<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanReview;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryActivityRecorder;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationWriteState;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ReviewTerritoryPlan
{
    public function __construct(private TerritoryCollaborationWriteState $state, private TerritoryCollaborationAuthorization $authorization,
        private TerritoryPlanSnapshotBuilder $snapshots, private TerritoryActivityRecorder $activities, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $planId, int $expectedRevision, string $snapshotChecksum, string $decision, ?string $note = null): string
    {
        Validator::make(['decision' => $decision, 'checksum' => $snapshotChecksum, 'note' => $note], [
            'decision' => ['required', 'in:approved,changes_requested'], 'checksum' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'note' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $snapshotChecksum, $decision, $note): string {
            $context = $this->state->lock($actorPlayerId, $planId, $expectedRevision);
            $this->authorization->authorizeReview($context);
            $checksum = $this->snapshots->checksum($this->snapshots->build($context->plan));
            if (! hash_equals($checksum, $snapshotChecksum)) {
                throw ValidationException::withMessages(['snapshot_checksum' => 'The reviewed content no longer matches the saved plan.']);
            }
            $review = TerritoryPlanReview::query()->firstOrCreate([
                'territory_plan_id' => $planId, 'reviewer_player_id' => $actorPlayerId, 'head_revision' => $expectedRevision,
            ], ['snapshot_checksum' => $checksum, 'decision' => $decision, 'note' => $note]);
            if ($review->decision !== $decision || $review->snapshot_checksum !== $checksum || $review->note !== $note) {
                throw ValidationException::withMessages(['review' => 'This revision already has a different review from you.']);
            }
            if ($review->wasRecentlyCreated) {
                $this->audit->record('territory.plan.reviewed', $context->actor, $context->plan, $context->plan->owner_alliance_id,
                    ['review_id' => (string) $review->id, 'head_revision' => $expectedRevision, 'decision' => $decision, 'snapshot_checksum' => $checksum]);
                $this->activities->record($context, 'reviewed', (string) $review->id, [(string) $context->plan->created_by_player_id]);
            }

            return (string) $review->id;
        });
    }
}
