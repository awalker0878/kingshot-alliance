<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration;

enum PlatformCatalogueKind: string
{
    case Alliances = 'alliances';
    case Administrators = 'administrators';
    case LegalHolds = 'legalHolds';
    case OutboxFailures = 'outboxFailures';
    case WebhookFailures = 'webhookFailures';
    case NotificationFailures = 'notificationFailures';
    case FailedJobs = 'failedJobs';
    case CorrelatedAudit = 'correlatedAudit';
    case Features = 'features';
}
