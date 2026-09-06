<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodePublicationFalsePositiveGuard
{
    public function hasExplicitGiftCodeLabel(string $content): bool
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/(?:^|\R)\s*(?:🎁\s*)?(?:gift\s*code|redeem\s*code)\s*[:：-]\s*[A-Za-z0-9_-]{3,64}\s*[.!]?\s*(?:\R|$)/iu', $text) === 1;
    }

    public function isUnsafeUrlOnlyCandidate(string $content): bool
    {
        return ! $this->hasExplicitGiftCodeLabel($content)
            && preg_match('/[?&]code=[A-Za-z0-9_-]{3,64}/iu', $content) === 1;
    }
}
