<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\ValueObjects;

final readonly class TerritoryPlanMutationReceipt
{
    /** @param array<string, mixed>|null $snapshot */
    public function __construct(
        public string $planId,
        public int $revision,
        public string $status,
        public ?string $publishedRevisionId = null,
        public ?array $snapshot = null,
        public ?string $layoutChecksum = null,
    ) {}
}
