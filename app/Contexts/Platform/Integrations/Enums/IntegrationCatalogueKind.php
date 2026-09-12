<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Enums;

enum IntegrationCatalogueKind: string
{
    case Credentials = 'credentials';
    case Webhooks = 'webhooks';
    case Deliveries = 'deliveries';
}
