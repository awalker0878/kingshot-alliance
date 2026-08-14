<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventPollStatus;
use App\Domain\Events\Enums\EventPollType;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPoll;
use App\Domain\Events\Models\EventPollOption;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEventPoll
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param list<array{label:string,value:string,metadata?:array<string,mixed>}>|null $options
     * @param array<string, mixed>|null $settings
     */
    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
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
        ?EventPoll $poll = null,
    ): EventPoll {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Polls);
        $this->authorization->authorizeManager($actor, $event);

        if ($poll instanceof EventPoll && (string) $poll->occurrence_id !== (string) $occurrence->id) {
            abort(404);
        }
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

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $poll, $key, $type, $question, $questionKey, $opensAt, $closesAt, $status, $maxChoices, $options, $settings, $target): EventPoll {
            $record = $poll instanceof EventPoll
                ? EventPoll::query()->whereKey($poll->id)->where('occurrence_id', $occurrence->id)->lockForUpdate()->firstOrFail()
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
                $record->created_by_player_id = $actor->id;
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
                'updated_by_player_id' => $actor->id,
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

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'poll_key' => $key,
                'poll_type' => $type->value,
                'status' => $status->value,
                'option_count' => $optionCount,
                'actor_player_id' => $actor->id,
            ];
            $eventName = $created ? 'event.poll.created' : 'event.poll.updated';
            $this->audit->record($eventName, $actor, $record, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh()->load(['options', 'votes']);
        });
    }

    /**
     * @param list<array{label:string,value:string,metadata?:array<string,mixed>}> $options
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
