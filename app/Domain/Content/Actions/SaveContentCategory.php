<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Services\ContentOutbox;
use App\Domain\Content\Services\ContentSanitizer;
use App\Domain\Identity\Models\User;
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
