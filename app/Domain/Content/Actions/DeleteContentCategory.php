<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteContentCategory
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $categoryId): void
    {
        DB::transaction(function () use ($alliance, $actor, $categoryId): void {
            $context = $this->authority->require($actor, $alliance, PermissionKey::ContentManage);

            $category = ContentCategory::query()
                ->where('id', $categoryId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($category->items()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Move or archive content before deleting this category.',
                ]);
            }

            $this->audit->record('content.category_deleted', $context->actor, $category, $context->alliance, [
                'slug' => $category->slug,
            ]);
            $this->outbox->record('content.category_deleted', (string) $context->alliance->id, $category, [
                'category_id' => $category->id,
                'slug' => $category->slug,
            ]);
            $category->delete();
        });
    }
}
