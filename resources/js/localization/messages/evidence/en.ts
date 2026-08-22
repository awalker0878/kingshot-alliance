import type { MessageCatalogue } from '../../types';

const messages = {
  evidence: {
    openIntake: 'Import battle report',
    eyebrow: 'Bear Hunt · Evidence review',
    title: 'Screenshot Intake',
    subtitle: 'Upload a Bear Hunt battle report. Kingshot Alliance keeps the original evidence and lets you review every extracted value before anything changes Event results.',
    back: 'Back to Bear Hunt',
    uploadTitle: 'Upload battle report',
    uploadHelp: 'JPEG, PNG, or WebP. The original stays private, is security-scanned, and receives an immutable checksum.',
    chooseFile: 'Battle-report screenshot',
    upload: 'Upload screenshot',
    uploading: 'Uploading…',
    existingTitle: 'Evidence for this Bear Hunt',
    empty: 'No screenshots have been uploaded for this Bear Hunt yet.',
    originalName: 'Source',
    status: 'Status',
    received: 'Received',
    security: 'Provenance',
  },
} satisfies MessageCatalogue;

export default messages;
