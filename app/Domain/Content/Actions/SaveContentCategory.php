<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Services\ContentSanitizer;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveContentCategory
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private ContentSanitizer $sanitizer,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $name,
        string $slug,
        int $sortOrder = 0,
        ?string $categoryId = null,
    ): ContentCategory {
        return DB::transaction(function () use ($alliance, $actor, $name, $slug, $sortOrder, $categoryId): ContentCategory {
            $context = $this->authority->require($actor, $alliance, PermissionKey::ContentManage);

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

            return $category->refresh();
        });
    }
}
