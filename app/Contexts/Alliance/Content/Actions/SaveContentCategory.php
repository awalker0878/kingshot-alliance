<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Services\ContentSanitizer;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveContentCategory
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private ContentSanitizer $sanitizer,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $name,
        string $slug,
        int $sortOrder = 0,
        ?string $categoryId = null,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $name, $slug, $sortOrder, $categoryId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);

            $category = $categoryId === null
                ? new ContentCategory(['alliance_id' => $context->alliance->id])
                : ContentCategory::query()
                    ->where('id', $categoryId)
                    ->where('alliance_id', $context->alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $category->forceFill([
                'name' => $this->sanitizer->line($name) ?? 'Category',
                'slug' => strtolower(trim($slug)),
                'sort_order' => max(0, $sortOrder),
            ])->save();

            $event = $categoryId === null ? 'content.category_created' : 'content.category_updated';
            $this->audit->record($event, $context->actor, $category, $context->alliance);
            $this->outbox->record($event, (string) $context->alliance->id, $category, [
                'category_id' => $category->id,
                'slug' => $category->slug,
            ]);

            return (string) $category->id;
        });
    }
}
