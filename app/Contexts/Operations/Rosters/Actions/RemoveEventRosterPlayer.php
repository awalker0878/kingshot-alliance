<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEventRosterPlayer
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private EventWorkflowGuard $workflows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $occurrenceId, string $memberId): void
    {
        DB::transaction(function () use ($actorPlayerId, $occurrenceId, $memberId): void {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->mutations->authorizeManager($context);
            $this->workflows->require($context->event, EventWorkflowDimension::Roster);

            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();
            $member = EventRosterMember::query()->whereKey($memberId)->lockForUpdate()->firstOrFail();
            EventRoster::query()->whereKey($member->roster_id)->where('occurrence_id', $occurrence->id)->sharedLock()->firstOrFail();

            if ($member->statusEnum() === EventRosterMemberStatus::Removed) {
                return;
            }

            $member->forceFill([
                'status' => EventRosterMemberStatus::Removed,
                'slot_number' => null,
                'removed_by_player_id' => $actorPlayerId,
                'removed_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_id' => (string) $member->roster_id,
                'player_id' => (string) $member->player_id,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record('event.roster.player_removed', $context->actor, $member, $context->target->allianceId, $metadata);
            $this->outbox->record('event.roster.player_removed', $context->target->allianceId, $member, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
