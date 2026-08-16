<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteContentCategory
{
    public function __construct(
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $categoryId): void
    {
        DB::transaction(function () use ($alliance, $actor, $categoryId): void {
            $context = $this->authority->require($actor, $alliance, AlliancePermission::ContentManage);

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
