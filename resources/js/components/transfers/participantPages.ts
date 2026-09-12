export type ParticipantPage<T> = {
  items: T[];
  nextCursor: string | null;
  hasMore: boolean;
  pageSize: number;
  isFirstPage: boolean;
};

export type ParticipantSummary = {
  total: number;
  incoming: number;
  outgoing: number;
  staying: number;
  completed: number;
  confirmed: number;
  withdrawn: number;
};
