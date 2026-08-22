<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\ValueObjects;

final readonly class TerritoryPlanMutationReceipt
{
    public function __construct(
        public string $planId,
        public int $revision,
        public string $status,
        public ?string $publishedRevisionId = null,
    ) {}
}
