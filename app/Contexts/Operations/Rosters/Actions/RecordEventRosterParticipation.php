<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordEventRosterParticipation
{
    public function __construct(
        private EventAuthorization $mutations,
        private EventParticipantAuthorization $participants,
        private EventCapabilityGuard $capabilities,
        private EventPlayerContextFreezer $contexts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        EventRosterMember $member,
        EventRosterMemberStatus $status,
    ): EventRosterMember {
        if (! in_array($status, [EventRosterMemberStatus::Participated, EventRosterMemberStatus::Absent], true)) {
            throw ValidationException::withMessages([
                'status' => 'Roster participation must be recorded as participated or absent.',
            ]);
        }

        $roster = $member->roster()->firstOrFail();
        $occurrence = $roster->occurrence()->firstOrFail();
        $event = $occurrence->event()->firstOrFail();

        return DB::transaction(function () use ($actor, $member, $status, $roster, $occurrence, $event): EventRosterMember {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Rosters);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRoster = EventRoster::query()
                ->whereKey($roster->id)
                ->where('occurrence_id', $lockedOccurrence->id)
                ->sharedLock()
                ->firstOrFail();
            $locked = EventRosterMember::query()
                ->whereKey($member->id)
                ->where('roster_id', $lockedRoster->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->statusEnum(), [EventRosterMemberStatus::Declined, EventRosterMemberStatus::Removed], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Declined or removed roster assignments cannot receive participation.',
                ]);
            }

            $player = (string) $context->actor->id === (string) $locked->player_id
                ? $context->actor
                : Player::query()->whereKey($locked->player_id)->firstOrFail();
            $frozenContext = $this->contexts->existing($lockedOccurrence, $player);

            if (! $frozenContext instanceof EventPlayerContext) {
                if ((string) $context->actor->id !== (string) $player->id) {
                    $player = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
                }
                if (! $this->participants->eligible($context->event, $player)) {
                    throw ValidationException::withMessages([
                        'player' => 'This Player is not eligible for the Event target.',
                    ]);
                }

                $representedAlliance = $locked->alliance_id === null
                    ? null
                    : Alliance::query()->whereKey($locked->alliance_id)->first();
                $this->contexts->freeze($lockedOccurrence, $player, $representedAlliance);
            }

            $locked->forceFill(['status' => $status])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'roster_id' => (string) $lockedRoster->id,
                'player_id' => (string) $locked->player_id,
                'status' => $status->value,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record('event.roster.participation.recorded', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.roster.participation.recorded',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scopeEnum()->value.':'.$context->target->id,
            );

            return $locked->refresh()->load(['player', 'roster']);
        });
    }
}
