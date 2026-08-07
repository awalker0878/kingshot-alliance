<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteContentCategory
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
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
            $this->outbox->record('content.category_deleted', (string) $alliance->id, $category, [
                'category_id' => $category->id,
                'slug' => $category->slug,
            ]);
            $category->delete();
        });
    }
}
