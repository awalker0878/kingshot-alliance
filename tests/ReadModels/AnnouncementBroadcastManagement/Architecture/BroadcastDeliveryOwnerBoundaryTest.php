<?php

declare(strict_types=1);

namespace Tests\ReadModels\AnnouncementBroadcastManagement\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Support\RepositoryPath;

final class BroadcastDeliveryOwnerBoundaryTest extends TestCase
{
    public function test_outcome_composition_uses_the_communications_contract_not_its_internal_tables(): void
    {
        $source = file_get_contents(RepositoryPath::fromRoot('app/ReadModels/AnnouncementBroadcastManagement/Queries/AnnouncementBroadcastManagementQuery.php'));
        self::assertIsString($source);
        self::assertStringContainsString('AnnouncementDeliverySummaryQuery', $source);
        foreach (['NotificationMessage', 'NotificationDelivery;', 'DeliveryStatus', 'notification_messages', 'notification_deliveries', 'DB::'] as $internal) {
            self::assertStringNotContainsString($internal, $source);
        }
    }
}
