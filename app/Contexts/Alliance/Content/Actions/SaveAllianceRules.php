<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\ContentRevisionWriter;
use App\Contexts\Alliance\Content\Services\ContentSanitizer;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveAllianceRules
{
    public const SLUG = 'alliance-rules';

    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private ContentSanitizer $sanitizer,
        private ContentRevisionWriter $revisions,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $body, string $locale): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $body, $locale): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);

            $rawBody = trim($body);
            $normalizedLocale = strtolower(trim($locale));
            $errors = [];

            if ($rawBody === '') {
                $errors['body'] = 'Alliance Rules cannot be empty.';
            } elseif (mb_strlen($rawBody) > 10000) {
                $errors['body'] = 'Alliance Rules cannot exceed 10,000 characters.';
            }

            if (
                $normalizedLocale === ''
                || mb_strlen($normalizedLocale) > 16
                || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/i', $normalizedLocale) !== 1
            ) {
                $errors['locale'] = 'The Rules language must be a valid locale code no longer than 16 characters.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $sanitizedBody = $this->sanitizer->body($rawBody);

            if (trim($sanitizedBody) === '') {
                throw ValidationException::withMessages([
                    'body' => 'Alliance Rules cannot be empty.',
                ]);
            }

            $item = ContentItem::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('slug', self::SLUG)
                ->lockForUpdate()
                ->first();

            if ($item instanceof ContentItem && $item->type !== ContentType::Rule) {
                throw ValidationException::withMessages([
                    'body' => 'The reserved Alliance Rules content identity is unavailable.',
                ]);
            }

            if (
                $item instanceof ContentItem
                && (string) $item->body === $sanitizedBody
                && strtolower((string) $item->locale) === $normalizedLocale
                && $item->status === ContentStatus::Published
                && $item->visibility === ContentVisibility::Members
                && $item->archived_at === null
            ) {
                return (string) $item->id;
            }

            $event = $item instanceof ContentItem ? 'content.rules.updated' : 'content.rules.created';

            if (! $item instanceof ContentItem) {
                $item = new ContentItem([
                    'alliance_id' => $context->alliance->id,
                    'created_by_player_id' => $context->actor->playerId,
                    'current_revision_number' => 1,
                ]);
            } else {
                $item->current_revision_number = (int) $item->current_revision_number + 1;
            }

            $item->forceFill([
                'category_id' => null,
                'type' => ContentType::Rule,
                'visibility' => ContentVisibility::Members,
                'status' => ContentStatus::Published,
                'title' => 'Alliance Rules',
                'slug' => self::SLUG,
                'summary' => null,
                'body' => $sanitizedBody,
                'locale' => $normalizedLocale,
                'sort_order' => 0,
                'notify_members' => false,
                'source_label' => null,
                'source_url' => null,
                'game_version' => null,
                'reviewed_at' => null,
                'context_links' => null,
                'scheduled_for' => null,
                'published_at' => $item->published_at ?? now(),
                'broadcasted_at' => null,
                'archived_at' => null,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $revision = $this->revisions->write($item, $context->actor);
            $metadata = [
                'content_item_id' => (string) $item->id,
                'revision_number' => (int) $revision->revision_number,
                'locale' => $normalizedLocale,
            ];

            $this->audit->record($event, $context->actor, $item, $context->alliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $context->alliance->id,
                $item,
                $metadata,
                $event.':'.$item->id.':'.$revision->revision_number,
            );

            return (string) $item->id;
        });
    }
}
