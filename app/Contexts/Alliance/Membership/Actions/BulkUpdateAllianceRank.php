<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use Throwable;

final readonly class BulkUpdateAllianceRank
{
    public function __construct(
        private PreviewBulkAllianceRankChange $preview,
        private UpdateAllianceRank $update,
    ) {}

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, mixed>
     */
    public function handle(string $allianceId, string $actorPlayerId, array $membershipIds, AllianceRank $rank): array
    {
        $preview = $this->preview->handle($allianceId, $actorPlayerId, $membershipIds, $rank);
        $items = [];
        foreach ($preview['items'] as $item) {
            if ($item['outcome'] !== 'ready') {
                $items[] = [...$item, 'outcome' => 'skipped'];

                continue;
            }
            try {
                $this->update->handle($allianceId, $actorPlayerId, (string) $item['itemId'], $rank);
                $items[] = [...$item, 'outcome' => 'succeeded', 'code' => 'updated'];
            } catch (Throwable) {
                $items[] = [...$item, 'outcome' => 'failed', 'code' => 'state_or_authority_changed'];
            }
        }

        return [
            'operation' => 'rank',
            'targetRank' => $rank->value,
            'items' => $items,
            'succeeded' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'succeeded')),
            'failed' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'failed')),
            'skipped' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'skipped')),
            'failedItemIds' => array_values(array_map(static fn (array $item): string => (string) $item['itemId'], array_filter($items, static fn (array $item): bool => $item['outcome'] === 'failed'))),
        ];
    }
}
