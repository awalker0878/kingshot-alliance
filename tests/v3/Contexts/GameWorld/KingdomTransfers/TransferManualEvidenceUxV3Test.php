<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use Tests\v3\TestCase;

final class TransferManualEvidenceUxV3Test extends TestCase
{
    public function test_manual_transfer_forms_do_not_offer_unbound_evidence_source(): void
    {
        foreach ([
            'resources/js/pages/Kingdom/Transfer/Manage.vue',
            'resources/js/pages/Kingdom/Transfer/Readiness.vue',
        ] as $path) {
            $source = file_get_contents(base_path($path));
            self::assertIsString($source, $path);

            // Evidence remains a valid persisted/history source type, but these manual forms
            // do not have an Intelligence/Evidence picker and therefore cannot create one.
            self::assertStringContainsString("| 'evidence'", $source, $path);

            $sourceTypesStart = strpos($source, 'const sourceTypes: SourceType[] = [');
            self::assertNotFalse($sourceTypesStart, $path);
            $sourceTypesEnd = strpos($source, '];', $sourceTypesStart);
            self::assertNotFalse($sourceTypesEnd, $path);
            $sourceTypes = substr(
                $source,
                $sourceTypesStart,
                $sourceTypesEnd - $sourceTypesStart + 2,
            );

            self::assertStringNotContainsString("'evidence'", $sourceTypes, $path);
            foreach (['in_game', 'official_publication', 'manager_note', 'community'] as $manualSource) {
                self::assertStringContainsString("'{$manualSource}'", $sourceTypes, $path);
            }
        }
    }
}
