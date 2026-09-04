<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Actions;

use Throwable;

final readonly class BulkChangeMembershipRole
{
    public function __construct(
        private PreviewBulkMembershipRoleChange $preview,
        private AssignMembershipRole $assign,
        private RemoveMembershipRole $remove,
    ) {}

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, mixed>
     */
    public function handle(string $allianceId, string $actorPlayerId, array $membershipIds, string $roleId, string $operation): array
    {
        $preview = $this->preview->handle($allianceId, $actorPlayerId, $membershipIds, $roleId, $operation);
        $items = [];
        foreach ($preview['items'] as $item) {
            if ($item['outcome'] !== 'ready') {
                $items[] = [...$item, 'outcome' => 'skipped'];

                continue;
            }
            try {
                if ($operation === 'assign') {
                    $this->assign->handle($allianceId, $actorPlayerId, (string) $item['itemId'], $roleId);
                } else {
                    $this->remove->handle($allianceId, $actorPlayerId, (string) $item['itemId'], $roleId);
                }
                $items[] = [...$item, 'outcome' => 'succeeded', 'code' => $operation === 'assign' ? 'assigned' : 'removed'];
            } catch (Throwable) {
                $items[] = [...$item, 'outcome' => 'failed', 'code' => 'state_or_authority_changed'];
            }
        }

        return [
            'operation' => $operation,
            'roleId' => $roleId,
            'items' => $items,
            'succeeded' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'succeeded')),
            'failed' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'failed')),
            'skipped' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'skipped')),
            'failedItemIds' => array_values(array_map(static fn (array $item): string => (string) $item['itemId'], array_filter($items, static fn (array $item): bool => $item['outcome'] === 'failed'))),
        ];
    }
}
