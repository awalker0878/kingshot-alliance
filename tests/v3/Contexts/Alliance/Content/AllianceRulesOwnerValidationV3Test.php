<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\SaveAllianceRules;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceRulesOwnerValidationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_owner_action_rejects_empty_or_oversized_rules_body(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $save = app(SaveAllianceRules::class);

        foreach (['   ', str_repeat('x', 10001)] as $body) {
            try {
                $save->handle($alliance, $owner, $body, 'en');
                self::fail('Expected invalid Alliance Rules body to be rejected by the owner Action.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('body', $exception->errors());
            }
        }

        $this->assertNoRulesSideEffects();
    }

    public function test_owner_action_rejects_invalid_or_empty_locale(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $save = app(SaveAllianceRules::class);

        foreach (['not_a_locale', '', str_repeat('a', 17)] as $locale) {
            try {
                $save->handle($alliance, $owner, 'Join Bear Hunt rallies on time.', $locale);
                self::fail('Expected invalid Alliance Rules locale to be rejected by the owner Action.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('locale', $exception->errors());
            }
        }

        $this->assertNoRulesSideEffects();
    }

    private function assertNoRulesSideEffects(): void
    {
        self::assertSame(0, ContentItem::query()->where('slug', SaveAllianceRules::SLUG)->count());
        self::assertSame(0, AuditEvent::query()->whereIn('event', ['content.rules.created', 'content.rules.updated'])->count());
        self::assertSame(0, OutboxMessage::query()->whereIn('event_type', ['content.rules.created', 'content.rules.updated'])->count());
    }

    /** @return array{0:string,1:string} */
    private function allianceScenario(): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId, 719001);
        $alliance = $scenarios->alliance($owner);

        return [$owner->playerId, $alliance->allianceId];
    }
}
