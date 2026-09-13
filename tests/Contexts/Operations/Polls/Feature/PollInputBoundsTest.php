<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Polls\Feature;

use App\Contexts\Operations\Polls\Actions\CastEventPollVote;
use App\Contexts\Operations\Polls\Actions\SaveEventPoll;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PollInputBoundsTest extends TestCase
{
    /** @return iterable<string,array{array<string,mixed>,string}> */
    public static function invalidPolls(): iterable
    {
        yield 'key length' => [['key' => str_repeat('a', 65)], 'key'];
        yield 'question length' => [['question' => str_repeat('a', 501)], 'question'];
        yield 'translation key length' => [['questionKey' => str_repeat('a', 181)], 'question_key'];
        yield 'raw option cardinality' => [['options' => array_fill(0, 51, ['label' => 'A', 'value' => 'a'])], 'options'];
        yield 'option map' => [['options' => ['first' => ['label' => 'A', 'value' => 'a']]], 'options'];
        yield 'option scalar' => [['options' => ['bad']], 'options'];
        yield 'label type' => [['options' => [['label' => ['bad'], 'value' => 'a']]], 'options'];
        yield 'label length' => [['options' => [['label' => str_repeat('a', 181), 'value' => 'a']]], 'options'];
        yield 'value length' => [['options' => [['label' => 'A', 'value' => str_repeat('a', 256)]]], 'options'];
        yield 'unknown option field' => [['options' => [['label' => 'A', 'value' => 'a', 'unexpected' => true]]], 'options'];
        yield 'nested metadata' => [['options' => [['label' => 'A', 'value' => 'a', 'metadata' => ['nested' => ['bad']]]]], 'options'];
        yield 'oversized metadata string' => [['options' => [['label' => 'A', 'value' => 'a', 'metadata' => ['label' => str_repeat('x', 501)]]]], 'options'];
        yield 'unknown settings' => [['settings' => ['unexpected' => true]], 'settings'];
        yield 'reminder outside bound' => [['settings' => ['deadline_reminder_minutes' => 10081]], 'deadline_reminder_minutes'];
        yield 'fractional reminder' => [['settings' => ['deadline_reminder_minutes' => 1.5]], 'deadline_reminder_minutes'];
        yield 'nonscalar reminder' => [['settings' => ['deadline_reminder_minutes' => ['bad']]], 'deadline_reminder_minutes'];
    }

    /** @param array<string,mixed> $overrides */
    #[DataProvider('invalidPolls')]
    public function test_invalid_direct_commands_reject_before_database_work(array $overrides, string $field): void
    {
        $arguments = array_replace([
            'actorPlayerId' => '01K00000000000000000000001', 'occurrenceId' => '01K00000000000000000000002',
            'key' => 'bounded', 'type' => EventPollType::Choice, 'question' => 'Choose a time',
            'options' => [['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']],
        ], $overrides);
        $this->withoutQueries(function () use ($arguments): void {
            app(SaveEventPoll::class)->handle(...$arguments);
        }, $field);
    }

    /** @return iterable<string,array{array<mixed>}> */
    public static function invalidVotes(): iterable
    {
        yield 'empty selection' => [[]];
        yield 'raw duplicates over budget' => [array_fill(0, 21, '01K00000000000000000000003')];
        yield 'map rather than list' => [['first' => '01K00000000000000000000003']];
        yield 'malformed identity' => [['not-an-option']];
        yield 'non-string identity' => [[123]];
    }

    /** @param array<mixed> $ids */
    #[DataProvider('invalidVotes')]
    public function test_raw_vote_inputs_reject_before_normalization_or_locks(array $ids): void
    {
        $this->withoutQueries(static function () use ($ids): void {
            app(CastEventPollVote::class)->handle('01K00000000000000000000001', '01K00000000000000000000002', '01K00000000000000000000003', $ids);
        }, 'options');
    }

    private function withoutQueries(callable $command, string $field): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            try {
                $command();
                self::fail('Malformed input must fail before owner acquisition.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($field, $exception->errors());
            }
            self::assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
