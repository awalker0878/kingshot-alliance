<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\v3\TestCase;

final class TransferEvidenceBoundaryV3Test extends TestCase
{
    public function test_transfer_evidence_extends_intelligence_evidence_without_new_ocr_context_or_generic_schema(): void
    {
        self::assertDirectoryDoesNotExist(base_path('app/Contexts/TransferOCR'));
        self::assertDirectoryDoesNotExist(base_path('app/Contexts/Intelligence/TransferOCR'));

        foreach (['app', 'database', 'routes'] as $root) {
            foreach ($this->phpFiles(base_path($root)) as $file) {
                $source = file_get_contents($file);
                self::assertIsString($source, $file);
                self::assertStringNotContainsString('transfer_ocr', strtolower($source), $file);
            }
        }
    }

    public function test_evidence_context_never_imports_kingdom_transfer_eloquent_models(): void
    {
        foreach ($this->phpFiles(base_path('app/Contexts/Intelligence/Evidence')) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);
            self::assertStringNotContainsString(
                'App\\Contexts\\GameWorld\\KingdomTransfers\\Models\\',
                $source,
                $file,
            );
        }
    }

    public function test_five_destination_actions_live_with_the_kingdom_transfer_owner(): void
    {
        foreach ([
            'RecordGovernorStatusEvidence.php',
            'RecordTransferScorePassEvidence.php',
            'RecordTransferInvitationEvidence.php',
            'RecordTransferKingdomRulesEvidence.php',
            'RecordOfficialTransferGroupEvidence.php',
        ] as $file) {
            $path = base_path('app/Contexts/GameWorld/KingdomTransfers/Actions/'.$file);
            self::assertFileExists($path);
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringContainsString('TransferEvidenceDestinationSupport', $source, $file);
            self::assertStringContainsString('DB::transaction', $source, $file);
        }
    }

    public function test_v1_transfer_schemas_cannot_emit_in_game_rules_verified(): void
    {
        foreach ([
            'TransferEvidenceSchemaRegistry.php',
            'TransferGovernorStatusExtractor.php',
            'TransferScorePassesExtractor.php',
            'TransferInvitationExtractor.php',
            'TransferTargetKingdomRulesExtractor.php',
            'TransferOfficialGroupExtractor.php',
        ] as $file) {
            $source = file_get_contents(base_path('app/Contexts/Intelligence/Evidence/Services/'.$file));
            self::assertIsString($source);
            self::assertStringNotContainsString('in_game_rules_verified', $source, $file);
        }
    }

    public function test_participant_workflow_embeds_transfer_evidence_while_manual_source_picker_remains_narrow(): void
    {
        $readiness = (string) file_get_contents(base_path('resources/js/pages/Kingdom/Transfer/Readiness.vue'));
        self::assertStringContainsString('TransferEvidencePanel', $readiness);
        self::assertStringContainsString(':participant-id="p.id"', $readiness);
        self::assertStringContainsString("| 'evidence'", $readiness);

        $sourceTypesStart = strpos($readiness, 'const sourceTypes: SourceType[] = [');
        self::assertNotFalse($sourceTypesStart);
        $sourceTypesEnd = strpos($readiness, '];', $sourceTypesStart);
        self::assertNotFalse($sourceTypesEnd);
        $manualSourceTypes = substr($readiness, $sourceTypesStart, $sourceTypesEnd - $sourceTypesStart + 2);
        self::assertStringNotContainsString("'evidence'", $manualSourceTypes);
    }

    public function test_transfer_evidence_mutations_are_password_confirmed_and_reads_are_scoped(): void
    {
        $routes = (string) file_get_contents(base_path('routes/kingdoms.php'));
        foreach (['store', 'review', 'resolveDuplicate', 'commit', 'retry', 'destroy'] as $method) {
            self::assertStringContainsString("TransferEvidenceController::class, '{$method}'", $routes);
        }
        foreach (['index', 'image', 'preview'] as $method) {
            self::assertStringContainsString("TransferEvidenceController::class, '{$method}'", $routes);
        }
        self::assertStringContainsString("Route::middleware('password.confirm')->group", $routes);
    }

    /** @return list<string> */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        return $files;
    }
}
