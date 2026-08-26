<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\TransferEvidenceSchema;
use InvalidArgumentException;

final class TransferEvidenceSchemaRegistry
{
    public function require(EvidenceKind $kind): TransferEvidenceSchema
    {
        return match ($kind) {
            EvidenceKind::TransferGovernorStatus => new TransferEvidenceSchema(
                kind: $kind,
                version: 'transfer-governor-status/1',
                supportedFields: ['governor_power'],
                requiredFields: ['governor_power'],
                minimumClassificationConfidence: 0.55,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'transfer-governor-status-v1',
                destinationAction: 'RecordGovernorStatusEvidence',
            ),
            EvidenceKind::TransferScorePasses => new TransferEvidenceSchema(
                kind: $kind,
                version: 'transfer-score-passes/1',
                supportedFields: ['transfer_score', 'transfer_passes_available', 'transfer_passes_required'],
                requiredFields: ['transfer_score', 'transfer_passes_available', 'transfer_passes_required'],
                minimumClassificationConfidence: 0.55,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'transfer-score-passes-v1',
                destinationAction: 'RecordTransferScorePassEvidence',
            ),
            EvidenceKind::TransferInvitation => new TransferEvidenceSchema(
                kind: $kind,
                version: 'transfer-invitation/1',
                supportedFields: ['invitation_status', 'target_kingdom_number'],
                requiredFields: ['invitation_status'],
                minimumClassificationConfidence: 0.55,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'transfer-invitation-v1',
                destinationAction: 'RecordTransferInvitationEvidence',
            ),
            EvidenceKind::TransferTargetKingdomRules => new TransferEvidenceSchema(
                kind: $kind,
                version: 'transfer-target-kingdom-rules/1',
                supportedFields: ['target_kingdom_number', 'power_cap', 'kingdom_classification'],
                requiredFields: ['target_kingdom_number', 'power_cap'],
                minimumClassificationConfidence: 0.55,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'transfer-target-kingdom-rules-v1',
                destinationAction: 'RecordTransferKingdomRulesEvidence',
            ),
            EvidenceKind::TransferOfficialGroup => new TransferEvidenceSchema(
                kind: $kind,
                version: 'transfer-official-group/1',
                supportedFields: ['official_group_identifier', 'kingdom_number'],
                requiredFields: ['official_group_identifier', 'kingdom_number'],
                minimumClassificationConfidence: 0.55,
                minimumFieldConfidence: 0.55,
                fixtureCorpus: 'transfer-official-group-v1',
                destinationAction: 'RecordOfficialTransferGroupEvidence',
            ),
            default => throw new InvalidArgumentException('Evidence kind is not a Transfer screenshot schema.'),
        };
    }
}
