<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryObjectComment;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCollaborationWriteState;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CommentOnTerritoryObject
{
    public function __construct(private TerritoryCollaborationWriteState $state, private TerritoryCollaborationAuthorization $authorization,
        private TerritoryPlanSnapshotBuilder $snapshots, private AuditRecorder $audit) {}

    public function handle(string $actorPlayerId, string $planId, int $expectedRevision, string $objectKey, string $body): string
    {
        $body = trim($body);
        Validator::make(['object_key' => $objectKey, 'body' => $body], [
            'object_key' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:4000'],
        ])->validate();

        return DB::transaction(function () use ($actorPlayerId, $planId, $expectedRevision, $objectKey, $body): string {
            $context = $this->state->lock($actorPlayerId, $planId, $expectedRevision);
            $snapshot = $this->snapshots->build($context->plan);
            $object = collect($snapshot['objects'])->firstWhere('key', $objectKey);
            if (! is_array($object)) {
                throw ValidationException::withMessages(['object_key' => 'Select a current object.']);
            }
            $this->authorization->authorizeReview($context, (string) $object['alliance_key']);
            $comment = TerritoryObjectComment::query()->create([
                'territory_plan_id' => $planId, 'object_key' => $objectKey, 'alliance_key' => $object['alliance_key'],
                'author_player_id' => $actorPlayerId, 'head_revision' => $expectedRevision,
                'object_snapshot' => $object, 'body' => $body,
            ]);
            $this->audit->record('territory.object.commented', $context->actor, $context->plan, $context->plan->owner_alliance_id,
                ['comment_id' => (string) $comment->id, 'object_key' => $objectKey, 'head_revision' => $expectedRevision]);

            return (string) $comment->id;
        });
    }
}
