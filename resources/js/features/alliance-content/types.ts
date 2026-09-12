export type ContentPageInfo = {
  nextCursor: string | null;
  hasMore: boolean;
  pageSize: number;
  isFirstPage: boolean;
  total: number;
};
export type ContentRevisionRow = {
  id: string;
  revisionNumber: number;
  title: string;
  createdAt: string | null;
};
export type BroadcastRun = {
  id: string;
  scheduleId: string | null;
  scheduledFor: string;
  status: 'pending' | 'queued' | 'empty' | 'cancelled';
  recipientCount: number;
  skippedCount: number;
  suppressedCount: number;
  replayedCount: number;
  deliveryCount: number;
  deliveryCounts: Record<string, number>;
  readCount: number;
  retryCandidateCount: number;
  failedDeliveryIds: string[];
  queuedAt: string | null;
};
