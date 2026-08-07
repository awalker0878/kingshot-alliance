<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\ContentCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class SaveContentCategory
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ContentSanitizer $sanitizer,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $name,
        string $slug,
        int $sortOrder = 0,
        ?string $categoryId = null,
    ): ContentCategory {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $name, $slug, $sortOrder, $categoryId): ContentCategory {
            $category = $categoryId === null
                ? new ContentCategory(['alliance_id' => $alliance->id])
                : ContentCategory::query()
                    ->where('id', $categoryId)
                    ->where('alliance_id', $alliance->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            $category->forceFill([
                'name' => $this->sanitizer->line($name) ?? 'Category',
                'slug' => strtolower(trim($slug)),
                'sort_order' => max(0, $sortOrder),
            ])->save();

            $event = $categoryId === null ? 'content.category_created' : 'content.category_updated';
            $this->audit->record($event, $actor, $category, $alliance);
            $this->outbox->record($event, $alliance, $category, [
                'category_id' => $category->id,
                'slug' => $category->slug,
            ]);

            return $category->refresh();
        });
    }
}
