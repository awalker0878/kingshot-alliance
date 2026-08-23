<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceReferenceGuard;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class TransferEvidenceBoundaryV3Test extends TestCase
{
    public function test_evidence_owner_lookup_is_bound_through_its_contract(): void
    {
        self::assertInstanceOf(EvidenceReferenceLookup::class, app(EvidenceReferenceLookup::class));
    }

    public function test_evidence_source_requires_same_alliance_latest_approved_reference(): void
    {
        $guard = new TransferEvidenceReferenceGuard($this->lookup());

        $this->assertEvidenceValidation(
            fn () => $guard->assertUsable('alliance-a', TransferSourceType::Evidence, null),
        );
        $this->assertEvidenceValidation(
            fn () => $guard->assertUsable('alliance-a', TransferSourceType::Evidence, 'foreign-approved'),
        );
        $this->assertEvidenceValidation(
            fn () => $guard->assertUsable('alliance-a', TransferSourceType::Evidence, 'own-pending'),
        );

        self::assertSame(
            'own-approved',
            $guard->assertUsable('alliance-a', TransferSourceType::Evidence, 'own-approved'),
        );
    }

    public function test_optional_evidence_reference_cannot_cross_alliance_even_for_other_source_types(): void
    {
        $guard = new TransferEvidenceReferenceGuard($this->lookup());

        self::assertNull($guard->assertUsable(
            'alliance-a',
            TransferSourceType::OfficialPublication,
            null,
        ));
        self::assertSame(
            'own-pending',
            $guard->assertUsable(
                'alliance-a',
                TransferSourceType::OfficialPublication,
                ' own-pending ',
            ),
        );
        $this->assertEvidenceValidation(
            fn () => $guard->assertUsable(
                'alliance-a',
                TransferSourceType::InGame,
                'foreign-approved',
            ),
        );
    }

    public function test_every_transfer_write_that_accepts_evidence_uses_the_owner_reference_guard(): void
    {
        $paths = [
            'app/Contexts/GameWorld/KingdomTransfers/Actions/SaveTransferWindow.php',
            'app/Contexts/GameWorld/KingdomTransfers/Actions/SaveTransferGroup.php',
            'app/Contexts/GameWorld/KingdomTransfers/Actions/RecordTransferKingdomCondition.php',
            'app/Contexts/GameWorld/KingdomTransfers/Actions/RecordTransferObservation.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));
            self::assertIsString($source, $path);
            self::assertStringContainsString('TransferEvidenceReferenceGuard', $source, $path);
            self::assertStringContainsString('assertUsable', $source, $path);
        }
    }

    private function lookup(): EvidenceReferenceLookup
    {
        return new class implements EvidenceReferenceLookup
        {
            public function belongsToAlliance(string $evidenceId, string $allianceId): bool
            {
                return $allianceId === 'alliance-a'
                    && in_array($evidenceId, ['own-approved', 'own-pending'], true);
            }

            public function isApprovedForAlliance(string $evidenceId, string $allianceId): bool
            {
                return $allianceId === 'alliance-a' && $evidenceId === 'own-approved';
            }
        };
    }

    /** @param callable(): mixed $callback */
    private function assertEvidenceValidation(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected the Evidence reference to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence_id', $exception->errors());
        }
    }
}
