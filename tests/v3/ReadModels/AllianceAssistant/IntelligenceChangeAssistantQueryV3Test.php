<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\AllianceAssistant;

use App\ReadModels\AllianceAssistant\Queries\IntelligenceChangeAssistantQuery;
use Tests\v3\TestCase;

final class IntelligenceChangeAssistantQueryV3Test extends TestCase
{
    public function test_change_language_is_bounded_and_does_not_hijack_normal_observation_questions(): void
    {
        $query = app(IntelligenceChangeAssistantQuery::class);

        self::assertTrue($query->supports('What changed with ABC Alliance?', null));
        self::assertTrue($query->supports('What intelligence is stale?', null));
        self::assertTrue($query->supports('Has my governor progression changed?', null));
        self::assertTrue($query->supports('What is the Bear Hunt performance trend?', null));

        self::assertFalse($query->supports('What do we know about ABC Alliance?', null));
        self::assertFalse($query->supports('What is our Bear Hunt guide?', null));
        self::assertFalse($query->supports('Can I transfer to Kingdom 123?', null));
        self::assertFalse($query->supports('ABC is preparing to attack us', null));
    }
}
