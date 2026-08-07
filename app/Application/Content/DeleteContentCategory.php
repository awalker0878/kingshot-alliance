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
use Illuminate\Validation\ValidationException;

final readonly class DeleteContentCategory
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $categoryId): void
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($alliance, $actor, $categoryId): void {
            $category = ContentCategory::query()
                ->where('id', $categoryId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($category->items()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Move or archive content before deleting this category.',
                ]);
            }

            $this->audit->record('content.category_deleted', $actor, $category, $alliance, [
                'slug' => $category->slug,
            ]);
            $this->outbox->record('content.category_deleted', $alliance, $category, [
                'category_id' => $category->id,
                'slug' => $category->slug,
            ]);
            $category->delete();
        });
    }
}
