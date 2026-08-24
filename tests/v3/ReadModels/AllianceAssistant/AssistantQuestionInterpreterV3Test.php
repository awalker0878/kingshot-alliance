<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\Services\AssistantQuestionInterpreter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\TestCase;

final class AssistantQuestionInterpreterV3Test extends TestCase
{
    #[DataProvider('supportedQuestions')]
    public function test_supported_free_form_questions_map_to_closed_intents(
        string $question,
        AssistantIntent $intent,
        ?string $subject,
        bool $includeTime,
        bool $nextEvent,
    ): void {
        $parsed = app(AssistantQuestionInterpreter::class)->interpret($question);

        self::assertSame($intent, $parsed->intent);
        self::assertSame($subject, $parsed->subject);
        self::assertSame($includeTime, $parsed->includeEventTime);
        self::assertSame($nextEvent, $parsed->nextEvent);
    }

    /** @return iterable<string, array{string,AssistantIntent,?string,bool,bool}> */
    public static function supportedQuestions(): iterable
    {
        yield 'event and roster' => [
            'What time is Swordland and am I rostered?',
            AssistantIntent::EventRosterSelf,
            'swordland',
            true,
            false,
        ];
        yield 'next event' => [
            'What is my next Event?',
            AssistantIntent::EventTime,
            null,
            true,
            true,
        ];
        yield 'guide' => [
            'What does our Bear Hunt guide say?',
            AssistantIntent::AllianceContent,
            'bear hunt',
            false,
            false,
        ];
        yield 'observation' => [
            'What have we observed about K123?',
            AssistantIntent::AllianceObservation,
            'k123',
            false,
            false,
        ];
    }

    #[DataProvider('localizedPromptCases')]
    public function test_closed_prompt_identifiers_make_localized_buttons_deterministic(
        AssistantPrompt $prompt,
        AssistantIntent $intent,
        ?string $subject,
    ): void {
        $parsed = app(AssistantQuestionInterpreter::class)->interpret('Texte localisé sans grammaire anglaise', $prompt);

        self::assertSame($intent, $parsed->intent);
        self::assertSame($subject, $parsed->subject);
    }

    /** @return iterable<string, array{AssistantPrompt,AssistantIntent,?string}> */
    public static function localizedPromptCases(): iterable
    {
        yield 'Swordland roster' => [AssistantPrompt::SwordlandRoster, AssistantIntent::EventRosterSelf, 'Swordland'];
        yield 'next event' => [AssistantPrompt::NextEvent, AssistantIntent::EventTime, null];
        yield 'Bear Hunt guide' => [AssistantPrompt::BearHuntGuide, AssistantIntent::AllianceContent, 'Bear Hunt'];
        yield 'observation' => [AssistantPrompt::Observation, AssistantIntent::AllianceObservation, 'opponent'];
    }

    public function test_write_like_question_is_unsupported_even_with_a_read_prompt_identifier(): void
    {
        $parsed = app(AssistantQuestionInterpreter::class)->interpret(
            'Put me on the Swordland roster',
            AssistantPrompt::SwordlandRoster,
        );

        self::assertSame(AssistantIntent::Unsupported, $parsed->intent);
    }

    public function test_generic_unsourced_game_question_is_unsupported(): void
    {
        $parsed = app(AssistantQuestionInterpreter::class)->interpret('What are the best heroes in KingShot?');

        self::assertSame(AssistantIntent::Unsupported, $parsed->intent);
    }
}
