<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;

final readonly class ExpireGiftCodes
{
    public function __construct(private ReconcileGiftCodeStatus $reconcile) {}

    public function handle(int $limit = 100): int
    {
        $ids = GiftCode::query()
            ->where('status', '!=', GiftCodeStatus::Expired->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        foreach ($ids as $giftCodeId) {
            $this->reconcile->handle($giftCodeId);
        }

        return count($ids);
    }
}
