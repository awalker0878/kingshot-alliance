<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Actions;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Polls\Models\EventPollOption;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPoll
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  list<array{label:string,value:string,metadata?:array<string,mixed>}>|null  $options
     * @param  array<string, mixed>|null  $settings
     */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $key,
        EventPollType $type,
        ?string $question = null,
        ?string $questionKey = null,
        ?CarbonImmutable $opensAt = null,
        ?CarbonImmutable $closesAt = null,
        EventPollStatus $status = EventPollStatus::Draft,
        int $maxChoices = 1,
        ?array $options = null,
        ?array $settings = null,
        ?string $pollId = null,
    ): string {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key)) {
            throw ValidationException::withMessages(['key' => 'Poll key must use lowercase letters, numbers, and hyphens.']);
        }
        if (($question === null || trim($question) === '') && ($questionKey === null || trim($questionKey) === '')) {
            throw ValidationException::withMessages(['question' => 'A poll question is required.']);
        }
        if ($opensAt !== null && $closesAt !== null && ! $closesAt->greaterThan($opensAt)) {
            throw ValidationException::withMessages(['closes_at' => 'Poll close time must be after open time.']);
        }
        if ($maxChoices < 1 || $maxChoices > 20) {
            throw ValidationException::withMessages(['max_choices' => 'Poll max choices must be between 1 and 20.']);
        }
        if ($type === EventPollType::TimeVote && $maxChoices !== 1) {
            throw ValidationException::withMessages(['max_choices' => 'Time voting allows one choice per Player.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $occurrenceId, $pollId, $key, $type, $question, $questionKey, $opensAt, $closesAt, $status, $maxChoices, $options, $settings): string {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $occurrence = EventOccurrence::query()
                ->whereKey($occurrenceId)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            $record = $pollId !== null
                ? EventPoll::query()->whereKey($pollId)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
                : new EventPoll(['occurrence_id' => $occurrence->id]);
            $created = ! $record->exists;
            $hasVotes = $record->exists && $record->votes()->exists();
            if ($hasVotes && $options !== null) {
                throw ValidationException::withMessages(['options' => 'Poll options cannot change after voting has started.']);
            }

            $normalizedOptions = $options === null ? null : $this->normalizeOptions($type, $options);
            $optionCount = $normalizedOptions === null
                ? ($record->exists ? $record->options()->count() : 0)
                : count($normalizedOptions);
            if ($status === EventPollStatus::Open && $optionCount < 2) {
                throw ValidationException::withMessages(['options' => 'An open poll requires at least two options.']);
            }
            if ($status === EventPollStatus::Open && $closesAt !== null && ! $closesAt->greaterThan(CarbonImmutable::now('UTC'))) {
                throw ValidationException::withMessages(['closes_at' => 'An open poll must close in the future.']);
            }
            if ($maxChoices > max(1, $optionCount)) {
                throw ValidationException::withMessages(['max_choices' => 'Max choices cannot exceed the number of poll options.']);
            }

            if ($created) {
                $record->created_by_player_id = $actorPlayerId;
            }
            $record->forceFill([
                'key' => $key,
                'poll_type' => $type,
                'question_key' => $questionKey === null || trim($questionKey) === '' ? null : trim($questionKey),
                'question' => $question === null || trim($question) === '' ? null : trim($question),
                'opens_at' => $opensAt?->utc(),
                'closes_at' => $closesAt?->utc(),
                'status' => $status,
                'max_choices' => $maxChoices,
                'settings' => $settings ?? ($record->settings ?? null),
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            if ($normalizedOptions !== null) {
                $record->options()->delete();
                foreach ($normalizedOptions as $index => $option) {
                    EventPollOption::query()->create([
                        'poll_id' => $record->id,
                        'label' => $option['label'],
                        'value' => $option['value'],
                        'sort_order' => $index,
                        'metadata' => $option['metadata'] ?? null,
                    ]);
                }
            }

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'poll_key' => $key,
                'poll_type' => $type->value,
                'status' => $status->value,
                'option_count' => $optionCount,
                'actor_player_id' => $actorPlayerId,
            ];
            $eventName = $created ? 'event.poll.created' : 'event.poll.updated';
            $this->audit->record($eventName, $context->actor, $record, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $record, $metadata, partitionKey: $context->target->partitionKey());

            return (string) $record->id;
        });
    }

    /**
     * @param  list<array{label:string,value:string,metadata?:array<string,mixed>}>  $options
     * @return list<array{label:string,value:string,metadata?:array<string,mixed>}>
     */
    private function normalizeOptions(EventPollType $type, array $options): array
    {
        $normalized = [];
        $seen = [];
        foreach ($options as $option) {
            $label = trim((string) ($option['label'] ?? ''));
            $value = trim((string) ($option['value'] ?? ''));
            if ($label === '' || $value === '') {
                throw ValidationException::withMessages(['options' => 'Every poll option requires a label and value.']);
            }
            if (isset($seen[$value])) {
                throw ValidationException::withMessages(['options' => 'Poll option values must be unique.']);
            }
            if ($type === EventPollType::TimeVote) {
                try {
                    CarbonImmutable::parse($value);
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['options' => 'Time-vote option values must be valid date-times.']);
                }
            }
            $seen[$value] = true;
            $normalized[] = ['label' => $label, 'value' => $value, 'metadata' => $option['metadata'] ?? []];
        }

        return $normalized;
    }
}
