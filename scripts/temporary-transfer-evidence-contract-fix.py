from pathlib import Path

fixture = Path('tests/Fixtures/Evidence/Transfer/transfer-target-kingdom-rules-v2.json')
text = fixture.read_text()
old = '"category":"missing_required_field","name":"age_threshold_missing","lines":["Kingdom #654","Power Cap 90,000,000","Hero Generation 4","Truegold Level 5","Ordinary Kingdom"],"expected_kind":"unknown"'
new = '"category":"missing_required_field","name":"age_threshold_missing","lines":["Kingdom #654","Power Cap 90,000,000","Hero Generation 4","Truegold Level 5","Ordinary Kingdom"],"expected_kind":"transfer_target_kingdom_rules"'
if old not in text:
    raise SystemExit('target-rules v2 missing-field fixture marker not found')
fixture.write_text(text.replace(old, new, 1))

test = Path('tests/v3/Contexts/Intelligence/Evidence/TransferEvidenceSchemasV3Test.php')
text = test.read_text()
old_actions = """        $expectedActions = [
            EvidenceKind::TransferGovernorStatus->value => 'RecordGovernorStatusEvidence',
            EvidenceKind::TransferScorePasses->value => 'RecordTransferScorePassEvidence',
            EvidenceKind::TransferInvitation->value => 'RecordTransferInvitationEvidence',
            EvidenceKind::TransferTargetKingdomRules->value => 'RecordTransferKingdomRulesEvidence',
            EvidenceKind::TransferOfficialGroup->value => 'RecordOfficialTransferGroupEvidence',
        ];

        foreach (EvidenceKind::transferCases() as $kind) {
            $schema = $registry->require($kind);
            self::assertStringEndsWith('/1', $schema->version);
"""
new_actions = """        $expectedActions = [
            EvidenceKind::TransferGovernorStatus->value => 'RecordGovernorStatusEvidence',
            EvidenceKind::TransferScorePasses->value => 'RecordTransferScorePassEvidence',
            EvidenceKind::TransferInvitation->value => 'RecordTransferInvitationEvidence',
            EvidenceKind::TransferTargetKingdomRules->value => 'RecordTransferKingdomRulesEvidence',
            EvidenceKind::TransferOfficialGroup->value => 'RecordOfficialTransferGroupEvidence',
        ];
        $expectedVersions = [
            EvidenceKind::TransferGovernorStatus->value => 'transfer-governor-status/1',
            EvidenceKind::TransferScorePasses->value => 'transfer-score-passes/1',
            EvidenceKind::TransferInvitation->value => 'transfer-invitation/1',
            EvidenceKind::TransferTargetKingdomRules->value => 'transfer-target-kingdom-rules/2',
            EvidenceKind::TransferOfficialGroup->value => 'transfer-official-group/1',
        ];

        foreach (EvidenceKind::transferCases() as $kind) {
            $schema = $registry->require($kind);
            self::assertSame($expectedVersions[$kind->value], $schema->version);
"""
if old_actions not in text:
    raise SystemExit('schema registry assertion marker not found')
test.write_text(text.replace(old_actions, new_actions, 1))
