<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactKind;
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

    /** @return iterable<string,array{string,AssistantIntent,?string,bool,bool}> */
    public static function supportedQuestions(): iterable
    {
        yield 'event and roster' => ['What time is Swordland and am I rostered?', AssistantIntent::EventRosterSelf, 'swordland', true, false];
        yield 'next event' => ['What is my next Event?', AssistantIntent::EventTime, null, true, true];
        yield 'guide' => ['What does our Bear Hunt guide say?', AssistantIntent::AllianceContent, 'bear hunt', false, false];
        yield 'observation' => ['What have we observed about K123?', AssistantIntent::AllianceObservation, 'k123', false, false];
        yield 'registration' => ['Did I register for Swordland?', AssistantIntent::EventParticipationSelf, 'swordland', false, false];
        yield 'assignment' => ['What is my Swordland assignment?', AssistantIntent::BattlePlanSelf, 'swordland', false, false];
        yield 'territory' => ['Which hive layout are we using for Bear Hunt?', AssistantIntent::TerritoryPlan, 'bear hunt', false, false];
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

    /** @return iterable<string,array{AssistantPrompt,AssistantIntent,?string}> */
    public static function localizedPromptCases(): iterable
    {
        yield 'Swordland roster' => [AssistantPrompt::SwordlandRoster, AssistantIntent::EventRosterSelf, 'Swordland'];
        yield 'next event' => [AssistantPrompt::NextEvent, AssistantIntent::EventTime, null];
        yield 'Bear Hunt guide' => [AssistantPrompt::BearHuntGuide, AssistantIntent::AllianceContent, 'Bear Hunt'];
        yield 'observation' => [AssistantPrompt::Observation, AssistantIntent::AllianceObservation, 'opponent'];
        yield 'game fact' => [AssistantPrompt::HeroFact, AssistantIntent::GameFact, null];
        yield 'RSVP week' => [AssistantPrompt::RsvpWeek, AssistantIntent::EventParticipationSelf, null];
        yield 'battle assignment' => [AssistantPrompt::BattleAssignment, AssistantIntent::BattlePlanSelf, 'Swordland'];
        yield 'transfer' => [AssistantPrompt::TransferStatus, AssistantIntent::TransferStatusSelf, null];
        yield 'territory' => [AssistantPrompt::TerritoryPlan, AssistantIntent::TerritoryPlan, 'Bear Hunt'];
    }

    public function test_recognized_roster_write_becomes_navigation_handoff_before_prompt_override(): void
    {
        $parsed = app(AssistantQuestionInterpreter::class)->interpret(
            'Put me on the Swordland roster',
            AssistantPrompt::SwordlandRoster,
        );

        self::assertSame(AssistantIntent::ActionHandoff, $parsed->intent);
        self::assertSame('roster', $parsed->writeAction);
        self::assertSame('swordland', $parsed->subject);
    }

    public function test_unknown_write_remains_unsupported(): void
    {
        self::assertSame(
            AssistantIntent::Unsupported,
            app(AssistantQuestionInterpreter::class)->interpret('Delete every Event')->intent,
        );
    }

    public function test_game_fact_forms_are_typed_without_creating_a_generic_game_fallback(): void
    {
        $hero = app(AssistantQuestionInterpreter::class)->interpret('What generation is Amadeus?');
        self::assertSame(AssistantIntent::GameFact, $hero->intent);
        self::assertSame(ProgressionFactKind::HeroGeneration, $hero->gameFact?->kind);

        $troop = app(AssistantQuestionInterpreter::class)->interpret('What are the stats for Infantry T3?');
        self::assertSame(AssistantIntent::GameFact, $troop->intent);
        self::assertSame(ProgressionFactKind::TroopTierStats, $troop->gameFact?->kind);
        self::assertSame(3, $troop->gameFact?->level);

        self::assertSame(
            AssistantIntent::Unsupported,
            app(AssistantQuestionInterpreter::class)->interpret('What are the best heroes in KingShot?')->intent,
        );
    }

    public function test_participation_assignment_and_roster_keywords_do_not_collide(): void
    {
        $waitlist = app(AssistantQuestionInterpreter::class)->interpret('Am I waitlisted?');
        self::assertSame(AssistantIntent::EventParticipationSelf, $waitlist->intent);
        self::assertSame('waitlist', $waitlist->participationMode);

        self::assertSame(
            AssistantIntent::BattlePlanSelf,
            app(AssistantQuestionInterpreter::class)->interpret('What team am I on?')->intent,
        );
        self::assertSame(
            AssistantIntent::EventRosterSelf,
            app(AssistantQuestionInterpreter::class)->interpret('Am I rostered for Swordland?')->intent,
        );
    }

    public function test_transfer_target_number_is_typed(): void
    {
        $parsed = app(AssistantQuestionInterpreter::class)->interpret('Can I transfer to Kingdom 123?');

        self::assertSame(AssistantIntent::TransferStatusSelf, $parsed->intent);
        self::assertSame(123, $parsed->kingdomNumber);
    }
}
