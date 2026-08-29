<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\ValueObjects\AllianceScopeReference;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Queries\AllianceOperationalAssistantQuery;
use App\ReadModels\AllianceAssistant\Services\AssistantQuestionInterpreter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceOperationalAssistantV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_command_and_progression_questions_use_authorized_command_evidence(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78501);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->firstOrFail();
        $scope = new AllianceScopeReference(
            $actor->playerId,
            $actor->kingdomId,
            $alliance->allianceId,
            (string) $membership->id,
        );
        $interpreter = app(AssistantQuestionInterpreter::class);
        $query = app(AllianceOperationalAssistantQuery::class);

        $attention = $query->ask(
            $actor,
            $scope,
            $interpreter->interpret('What needs attention in Alliance Command?'),
        );
        self::assertSame(AssistantIntent::AllianceCommandAttention, $attention->intent);
        self::assertNotEmpty($attention->evidence);
        self::assertSame('alliance_command', $attention->evidence[0]->sourceType->value);

        $freshness = $query->ask(
            $actor,
            $scope,
            $interpreter->interpret('Which Governor observations are stale or missing?'),
        );
        self::assertSame(AssistantIntent::ProgressionFreshness, $freshness->intent);
        self::assertCount(1, $freshness->evidence);
        self::assertSame('roster_freshness', $freshness->evidence[0]->sourceType->value);
        self::assertSame('/alliance/roster/intelligence', $freshness->evidence[0]->href);
    }
}
