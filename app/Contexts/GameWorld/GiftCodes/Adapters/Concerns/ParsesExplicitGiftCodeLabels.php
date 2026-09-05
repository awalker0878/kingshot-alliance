<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters\Concerns;

trait ParsesExplicitGiftCodeLabels
{
    /** @return list<string> */
    private function explicitGiftCodes(string $text): array
    {
        $codes = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match(
                '/^\s*(?:🎁\s*)?(?:gift\s*code|redeem\s*code)\s*[:：]\s*([A-Za-z0-9_-]{3,64})\s*[.!]?\s*$/iu',
                $line,
                $matches,
            ) !== 1) {
                continue;
            }
            $codes[] = $matches[1];
        }

        return array_values(array_unique($codes));
    }
}
