<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;

final class GiftCodeRewardPresenter
{
    /**
     * @return array{state:string,items:list<array{type:string,key:?string,label:?string,quantity:?int,durationSeconds:?int}>}
     */
    public function present(GiftCode $giftCode): array
    {
        $projection = $giftCode->relationLoaded('factProjections')
            ? $giftCode->factProjections->firstWhere('fact_type', 'reward')
            : GiftCodeFactProjection::query()->where('gift_code_id', $giftCode->id)->where('fact_type', 'reward')->first();

        if (! $projection instanceof GiftCodeFactProjection) {
            return ['state' => 'reward_details_unknown', 'items' => []];
        }
        if (! $projection->qualified) {
            return ['state' => $projection->reason_code, 'items' => []];
        }
        $value = $projection->value;
        if (! is_array($value) || ! is_array($value['items'] ?? null)) {
            return ['state' => 'reward_details_unknown', 'items' => []];
        }

        $allowed = ['resource', 'currency', 'speedup', 'hero_item', 'chest', 'other'];
        $items = [];
        foreach (array_slice($value['items'], 0, 50) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = is_string($item['type'] ?? null) ? $item['type'] : '';
            if (! in_array($type, $allowed, true)) {
                continue;
            }
            $key = is_string($item['key'] ?? null) ? mb_substr($item['key'], 0, 120) : null;
            $label = is_string($item['label'] ?? null) ? mb_substr($item['label'], 0, 160) : null;
            $quantity = is_numeric($item['quantity'] ?? null) ? max(0, (int) $item['quantity']) : null;
            $durationSeconds = is_numeric($item['duration_seconds'] ?? null)
                ? max(0, (int) $item['duration_seconds'])
                : null;
            $items[] = [
                'type' => $type,
                'key' => $key,
                'label' => $label,
                'quantity' => $quantity,
                'durationSeconds' => $durationSeconds,
            ];
        }

        return $items === []
            ? ['state' => 'reward_details_unknown', 'items' => []]
            : ['state' => 'qualified', 'items' => $items];
    }
}
