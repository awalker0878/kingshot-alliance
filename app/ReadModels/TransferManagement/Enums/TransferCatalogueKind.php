<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Enums;

enum TransferCatalogueKind: string
{
    case Windows = 'windows';
    case Plans = 'plans';
    case Groups = 'officialGroups';
    case Conditions = 'conditions';
    case Capacities = 'capacities';
    case Cohorts = 'cohorts';
}
