<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\ArchiveContentItem;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\RestoreContentRevision;
use App\Contexts\Alliance\Content\Actions\SaveAllianceRules;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceRulesOwnershipBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_rules_save_uses_the_exclusive_alliance_aggregate_lock(): void
    {
        $path = dirname(__DIR__, 5).'/app/Contexts/Alliance/Content/Actions/SaveAllianceRules.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('->lockExclusiveScope($actorPlayerId, $allianceId)', $source);
        self::assertStringNotContainsString('->lockActiveScope($actorPlayerId, $allianceId)', $source);
    }

    public function test_generic_content_flows_cannot_mutate_or_manage_canonical_alliance_rules(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $contentId = app(SaveAllianceRules::class)->handle(
            $alliance,
            $owner,
            'Join Bear Hunt rallies on time.',
            'en',
        );
        $revisionId = (string) ContentRevision::query()
            ->where('content_item_id', $contentId)
            ->firstOrFail()
            ->id;

        $attempts = [
            static fn () => app(SaveContentItem::class)->handle(
                $alliance,
                $owner,
                [
                    'category_id' => null,
                    'type' => ContentType::Rule,
                    'visibility' => ContentVisibility::Members,
                    'title' => 'Renamed Rules',
                    'slug' => 'renamed-alliance-rules',
                    'summary' => null,
                    'body' => 'Generic edit must not replace canonical Rules.',
                    'locale' => 'en',
                    'sort_order' => 0,
                    'notify_members' => false,
                    'source_label' => null,
                    'source_url' => null,
                    'game_version' => null,
                    'reviewed_at' => null,
                    'context_links' => [],
                ],
                $contentId,
            ),
            static fn () => app(PublishContentItem::class)->handle(
                $alliance,
                $owner,
                $contentId,
                now()->addHour(),
            ),
            static fn () => app(ArchiveContentItem::class)->handle($alliance, $owner, $contentId),
            static fn () => app(RestoreContentRevision::class)->handle(
                $alliance,
                $owner,
                $contentId,
                $revisionId,
            ),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                self::fail('Expected generic Content management to reject the canonical Alliance Rules item.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('content', $exception->errors());
            }
        }

        $item = ContentItem::query()->findOrFail($contentId);
        self::assertSame(ContentItem::ALLIANCE_RULES_SLUG, $item->slug);
        self::assertSame(ContentType::Rule, $item->type);
        self::assertSame(ContentStatus::Published, $item->status);
        self::assertSame('Join Bear Hunt rallies on time.', $item->body);
        self::assertSame(1, (int) $item->current_revision_number);
        self::assertSame(1, ContentRevision::query()->where('content_item_id', $contentId)->count());
        self::assertFalse(app(ContentQuery::class)->managerList($alliance)->contains('id', $contentId));

        self::assertSame(0, AuditEvent::query()->whereIn('event', [
            'content.updated',
            'content.scheduled',
            'content.published',
            'content.archived',
            'content.revision_restored',
        ])->count());
        self::assertSame(0, OutboxMessage::query()->whereIn('event_type', [
            'content.updated',
            'content.scheduled',
            'content.published',
            'content.archived',
            'content.revision_restored',
        ])->count());
    }

    /** @return array{0:string,1:string} */
    private function allianceScenario(): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId, 722001);
        $alliance = $scenarios->alliance($owner);

        return [$owner->playerId, $alliance->allianceId];
    }
}
