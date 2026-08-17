<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;

final readonly class AllianceDashboardCapabilitiesQuery
{
    public function __construct(private AllianceOperationsAuthorization $operationsAuthorization) {}

    /** @return array{canManageEvents: bool} */
    public function for(Player $actor, Alliance $alliance): array
    {
        return [
            'canManageEvents' => $this->operationsAuthorization->allows(
                $actor,
                $alliance,
                OperationsPermission::EventAllianceManage,
            ),
        ];
    }
}
