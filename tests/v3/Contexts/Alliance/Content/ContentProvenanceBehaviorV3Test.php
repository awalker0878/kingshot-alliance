<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\RestoreContentRevision;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class ContentProvenanceBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_content_requires_a_source_and_review_date(): void
    {
        [$owner, $alliance] = $this->allianceScenario();

        try {
            app(SaveContentItem::class)->handle($alliance, $owner, $this->guideAttributes([
                'source_label' => null,
                'reviewed_at' => null,
            ]));
            self::fail('Expected incomplete knowledge provenance to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('source_label', $exception->errors());
            self::assertArrayHasKey('reviewed_at', $exception->errors());
        }
    }

    public function test_provenance_is_versioned_and_restored_with_content(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $save = app(SaveContentItem::class);
        $contentId = $save->handle($alliance, $owner, $this->guideAttributes());
        $firstRevision = ContentRevision::query()
            ->where('content_item_id', $contentId)
            ->firstOrFail();

        $save->handle($alliance, $owner, $this->guideAttributes([
            'title' => 'Bear Hunt guide — revised',
            'source_label' => 'Alliance field review',
            'source_url' => null,
            'game_version' => '2026.08',
            'reviewed_at' => '2026-08-19',
        ]), $contentId);

        app(RestoreContentRevision::class)->handle(
            $alliance,
            $owner,
            $contentId,
            (string) $firstRevision->id,
        );

        $restored = ContentItem::query()->findOrFail($contentId);
        self::assertSame('Official Kingshot event page', $restored->source_label);
        self::assertSame('https://www.centurygames.com/kingshot-thursday-madness/', $restored->source_url);
        self::assertSame('2026.07', $restored->game_version);
        self::assertSame('2026-08-18', $restored->reviewed_at?->toDateString());
        self::assertSame(ContentStatus::Draft, $restored->status);
        self::assertCount(3, $restored->revisions()->get());
    }

    public function test_unreviewed_legacy_knowledge_cannot_be_published(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $item = ContentItem::query()->create([
            'alliance_id' => $alliance,
            'type' => ContentType::Guide,
            'visibility' => ContentVisibility::Members,
            'status' => ContentStatus::Draft,
            'title' => 'Legacy guide',
            'slug' => 'legacy-guide',
            'body' => 'Review this content before publication.',
            'locale' => 'en',
            'sort_order' => 0,
            'current_revision_number' => 1,
            'created_by_player_id' => $owner,
            'updated_by_player_id' => $owner,
        ]);

        $this->expectException(ValidationException::class);
        app(PublishContentItem::class)->handle($alliance, $owner, (string) $item->id);
    }

    public function test_revision_restore_recovers_announcement_broadcast_intent(): void
    {
        [$owner, $alliance] = $this->allianceScenario();
        $save = app(SaveContentItem::class);
        $attributes = [
            'category_id' => null,
            'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members,
            'title' => 'Rally announcement',
            'slug' => 'rally-announcement',
            'summary' => null,
            'body' => 'Rally at reset.',
            'locale' => 'en',
            'sort_order' => 0,
            'notify_members' => true,
        ];
        $contentId = $save->handle($alliance, $owner, $attributes);
        $firstRevision = ContentRevision::query()
            ->where('content_item_id', $contentId)
            ->firstOrFail();

        $save->handle($alliance, $owner, [...$attributes, 'notify_members' => false], $contentId);
        $item = ContentItem::query()->findOrFail($contentId);
        $item->forceFill(['broadcasted_at' => now()])->save();

        app(RestoreContentRevision::class)->handle(
            $alliance,
            $owner,
            $contentId,
            (string) $firstRevision->id,
        );

        $restored = $item->fresh();
        self::assertTrue((bool) $restored?->notify_members);
        self::assertNull($restored?->broadcasted_at);
    }

    /** @return array{0: string, 1: string} */
    private function allianceScenario(): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($owner);

        return [$owner->playerId, $alliance->allianceId];
    }

    /** @param array<string, mixed> $overrides */
    private function guideAttributes(array $overrides = []): array
    {
        return array_replace([
            'category_id' => null,
            'type' => ContentType::Guide,
            'visibility' => ContentVisibility::Members,
            'title' => 'Bear Hunt guide',
            'slug' => 'bear-hunt-guide',
            'summary' => 'A reviewed Event guide.',
            'body' => 'Follow the Alliance rally plan.',
            'locale' => 'en',
            'sort_order' => 0,
            'notify_members' => false,
            'source_label' => 'Official Kingshot event page',
            'source_url' => 'https://www.centurygames.com/kingshot-thursday-madness/',
            'game_version' => '2026.07',
            'reviewed_at' => '2026-08-18',
        ], $overrides);
    }
}
