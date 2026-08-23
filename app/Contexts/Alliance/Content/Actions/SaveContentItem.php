<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\ContentRevisionWriter;
use App\Contexts\Alliance\Content\Services\ContentSanitizer;
use App\Contexts\Alliance\Content\Services\DeactivateAnnouncementBroadcastSchedule;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class SaveContentItem
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private ContentSanitizer $sanitizer,
        private ContentRevisionWriter $revisions,
        private DeactivateAnnouncementBroadcastSchedule $deactivateBroadcastSchedule,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{
     *   category_id?: string|null,
     *   type: ContentType,
     *   visibility: ContentVisibility,
     *   title: string,
     *   slug: string,
     *   summary?: string|null,
     *   body: string,
     *   locale: string,
     *   sort_order?: int,
     *   notify_members?: bool,
     *   source_label?: string|null,
     *   source_url?: string|null,
     *   game_version?: string|null,
     *   reviewed_at?: string|null,
     *   context_links?: list<array{type:string,key:string}>
     * }  $attributes
     */
    public function handle(string $allianceId, string $actorPlayerId, array $attributes, ?string $contentItemId = null): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $attributes, $contentItemId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);

            if (strtolower(trim($attributes['slug'])) === SaveAllianceRules::SLUG) {
                throw ValidationException::withMessages([
                    'slug' => 'The alliance-rules address is reserved for the Alliance Rules workflow.',
                ]);
            }

            $categoryId = $attributes['category_id'] ?? null;
            $this->assertCategory((string) $context->alliance->id, $categoryId);
            $provenance = $this->normalizeProvenance($attributes);
            $contextLinks = $this->normalizeContextLinks($attributes['context_links'] ?? []);

            $item = $contentItemId === null
                ? new ContentItem([
                    'alliance_id' => $context->alliance->id,
                    'created_by_player_id' => $context->actor->playerId,
                    'current_revision_number' => 1,
                ])
                : ContentItem::query()
                    ->where('id', $contentItemId)
                    ->where('alliance_id', $context->alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if ($contentItemId !== null) {
                $item->current_revision_number = (int) $item->current_revision_number + 1;
            }

            $item->forceFill([
                'category_id' => $categoryId,
                'type' => $attributes['type'],
                'visibility' => $attributes['visibility'],
                'status' => ContentStatus::Draft,
                'title' => $this->sanitizer->line($attributes['title']) ?? 'Untitled',
                'slug' => strtolower(trim($attributes['slug'])),
                'summary' => $this->sanitizer->line($attributes['summary'] ?? null),
                'source_label' => $provenance['source_label'],
                'source_url' => $provenance['source_url'],
                'game_version' => $provenance['game_version'],
                'reviewed_at' => $provenance['reviewed_at'],
                'context_links' => $contextLinks === [] ? null : $contextLinks,
                'body' => $this->sanitizer->body($attributes['body']),
                'locale' => strtolower(trim($attributes['locale'])),
                'sort_order' => max(0, (int) ($attributes['sort_order'] ?? 0)),
                'notify_members' => $attributes['type'] === ContentType::Announcement
                    && (bool) ($attributes['notify_members'] ?? false),
                'scheduled_for' => null,
                'published_at' => null,
                'broadcasted_at' => null,
                'archived_at' => null,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            if ($contentItemId !== null) {
                $this->deactivateBroadcastSchedule->handle($item, $context->actor, 'content-revised');
            }

            $revision = $this->revisions->write($item, $context->actor);
            $event = $contentItemId === null ? 'content.created' : 'content.updated';

            $this->audit->record($event, $context->actor, $item, $context->alliance, [
                'revision_number' => $revision->revision_number,
                'visibility' => $item->visibility->value,
                'type' => $item->type->value,
                'notify_members' => (bool) $item->notify_members,
                'context_links' => $contextLinks,
            ]);
            $this->outbox->record($event, (string) $context->alliance->id, $item, [
                'content_item_id' => $item->id,
                'revision_number' => $revision->revision_number,
                'visibility' => $item->visibility->value,
                'type' => $item->type->value,
                'notify_members' => (bool) $item->notify_members,
                'context_links' => $contextLinks,
            ]);

            return (string) $item->id;
        });
    }

    /**
     * @param  array{
     *   type: ContentType,
     *   source_label?: string|null,
     *   source_url?: string|null,
     *   game_version?: string|null,
     *   reviewed_at?: string|null
     * }  $attributes
     * @return array{
     *   source_label: string|null,
     *   source_url: string|null,
     *   game_version: string|null,
     *   reviewed_at: CarbonImmutable|null
     * }
     */
    private function normalizeProvenance(array $attributes): array
    {
        $sourceLabel = $this->sanitizer->line($attributes['source_label'] ?? null);
        $sourceUrl = trim((string) ($attributes['source_url'] ?? '')) ?: null;
        $gameVersion = $this->sanitizer->line($attributes['game_version'] ?? null);
        $reviewedAtValue = trim((string) ($attributes['reviewed_at'] ?? ''));
        $reviewedAt = null;
        $errors = [];

        if ($sourceUrl !== null && (
            filter_var($sourceUrl, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($sourceUrl, PHP_URL_SCHEME)) !== 'https'
            || parse_url($sourceUrl, PHP_URL_HOST) === null
            || parse_url($sourceUrl, PHP_URL_USER) !== null
        )) {
            $errors['source_url'] = 'The source URL must be a credential-free HTTPS URL.';
        }

        if ($reviewedAtValue !== '') {
            try {
                $reviewedAt = CarbonImmutable::createFromFormat('!Y-m-d', $reviewedAtValue, 'UTC');
            } catch (Throwable) {
                $reviewedAt = null;
            }

            if (
                ! $reviewedAt instanceof CarbonImmutable
                || $reviewedAt->format('Y-m-d') !== $reviewedAtValue
                || $reviewedAt->isAfter(CarbonImmutable::today('UTC'))
            ) {
                $errors['reviewed_at'] = 'The review date must be a valid date that is not in the future.';
                $reviewedAt = null;
            }
        }

        if ($attributes['type']->requiresProvenance()) {
            if ($sourceLabel === null) {
                $errors['source_label'] = 'A source label is required for knowledge content.';
            }

            if ($reviewedAt === null) {
                $errors['reviewed_at'] ??= 'A review date is required for knowledge content.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'source_label' => $sourceLabel,
            'source_url' => $sourceUrl,
            'game_version' => $gameVersion,
            'reviewed_at' => $reviewedAt,
        ];
    }

    private function assertCategory(string $allianceId, ?string $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        $category = ContentCategory::query()
            ->where('id', $categoryId)
            ->where('alliance_id', $allianceId)
            ->sharedLock()
            ->first();

        if (! $category instanceof ContentCategory) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category does not belong to this alliance.',
            ]);
        }
    }

    /**
     * @param  list<array{type:string,key:string}>  $links
     * @return list<array{type:string,key:string}>
     */
    private function normalizeContextLinks(array $links): array
    {
        $normalized = [];
        $errors = [];

        foreach ($links as $index => $link) {
            $type = strtolower(trim($link['type'] ?? ''));
            $key = strtolower(trim($link['key'] ?? ''));

            if ($type !== 'event_type') {
                $errors["context_links.$index.type"] = 'The selected content context is not supported.';

                continue;
            }

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1 || strlen($key) > 120) {
                $errors["context_links.$index.key"] = 'The selected Event type context is invalid.';

                continue;
            }

            $normalized[$type.':'.$key] = ['type' => $type, 'key' => $key];
        }

        if (count($normalized) > 20) {
            $errors['context_links'] = 'Content may link to at most 20 contexts.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        ksort($normalized);

        return array_values($normalized);
    }
}
