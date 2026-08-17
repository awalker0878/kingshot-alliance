<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;

final readonly class AllianceDashboardCapabilitiesQuery
{
    public function __construct(private AllianceOperationsAuthorization $operationsAuthorization) {}

    /** @return array{canManageEvents: bool} */
    public function for(string $actorPlayerId, string $allianceId): array
    {
        return [
            'canManageEvents' => $this->operationsAuthorization->allows(
                $actorPlayerId,
                $allianceId,
                OperationsPermission::EventAllianceManage,
            ),
        ];
    }
}
