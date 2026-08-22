import type { MessageCatalogue } from '../../types';

const messages = {
  territory: {
    eyebrow: 'Territory Command', indexTitle: 'Territory Command', editorTitle: 'Hive Builder',
    indexSubtitle: 'Plan the Kingdom map as {governor}, then publish a layout your officers can actually coordinate from.',
    editorSubtitle: '{governor} is editing the working layout. Published revisions remain fixed.',
    savedPlans: 'Saved layouts', plansHeading: 'Territory plans', noPlans: 'No territory plans yet. Start with the Alliance hive you actually want to build in game.',
    newPlan: 'New layout', createPlan: 'Create territory plan', scope: 'Planning scope', alliancePlan: 'Alliance hive plan', kingdomPlan: 'Kingdom plan', alliance: 'Alliance', planName: 'Plan name', mapProfile: 'KingShot map profile', mapEvidenceHelp: 'Map coordinates and rules show their source and observation boundary. Community-observed data is useful planning evidence, not an official Century Games claim.',
    updated: 'Updated {date}', revision: 'Working revision {revision}', backToPlans: 'All territory plans', mapSource: 'View map source',
    build: 'Build', select: 'Select', pan: 'Pan', place: 'Place', activeAlliance: 'Active Alliance', objectType: 'Place object',
    types: { headquarters: 'Alliance HQ', banner: 'Alliance Banner', governor_city: 'Governor City', bear_trap: 'Bear Trap' },
    swirlHive: 'Generate swirl hive', bannerPadHive: 'Generate banner-pad hive', addAlliance: 'Add external Alliance', externalAlliance: 'External Alliance',
    editing: 'Edit selection', undo: 'Undo', redo: 'Redo', duplicate: 'Duplicate', group: 'Group', ungroup: 'Ungroup', rotate: 'Rotate 90°', deleteSelected: 'Delete selected',
    validation: 'Placement check', invalid: 'Blocked', validWithWarnings: 'Valid with planning warnings', valid: 'Valid', noValidationIssues: 'No placement violations or planning warnings in the current working layout.', fixViolations: 'Fix blocking placement violations before saving.',
    analyze: 'Analyze', layoutAnalysis: 'Layout analysis', coverage: 'Cities covered', uncovered: 'Uncovered cities', components: 'Territory chains', avgDistance: 'Avg Bear distance', maxDistance: 'Longest Bear distance', bannerEfficiency: 'Covered cities / Banner',
    preferences: 'Planning assumptions', preferredBearRadius: 'Preferred Bear radius (tiles)', marchSecondsPerTile: 'March seconds per tile', marchAssumptionHelp: 'March speed is a planning assumption unless the selected map profile explicitly identifies a sourced game value. It is never silently presented as official.',
    selection: 'Exact placement', selectedCount: '{count} object(s) selected', save: 'Save working layout', saved: 'Working layout saved as revision {revision}.', publish: 'Publish revision', published: 'Published a fixed territory-plan revision.', archive: 'Archive plan', archiveConfirm: 'Archive this territory plan? Published revisions remain historical records.',
    exportJson: 'Export JSON', exportPng: 'Export PNG', exportSvg: 'Export SVG', importJson: 'Import JSON', importReady: 'Import parsed and validated. Review the preview before applying it.', importAppliedUnsaved: 'Imported layout applied to working state. Save it to make the change durable.', importPreview: 'Import preview is ready.', applyImport: 'Apply to working layout', revisions: 'Published revisions', requestFailed: 'Territory Command could not complete that request.',
    status: { draft: 'Draft', published: 'Published', archived: 'Archived' },
    confidence: { official: 'Official source', verified_observation: 'Verified observation', community_observed: 'Community-observed' },
  },
} satisfies MessageCatalogue;
export default messages;
