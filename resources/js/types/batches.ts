import type { HorizonStatus } from "@/types/dashboard";
import type { JobRow } from "@/types/jobs";

export type BatchStatus = "pending" | "failures" | "finished" | "cancelled";
export type BatchCreatedRange = "hour" | "day" | "week" | "month";

export type BatchFilterValues = {
  queue: string | null;
  connection: string | null;
  created: BatchCreatedRange | null;
};

export type BatchRow = {
  id: string;
  name: string | null;
  displayName: string;
  connection?: string | null;
  queue?: string | null;
  totalJobs: number;
  pendingJobs: number;
  failedJobs: number;
  processedJobs: number;
  progress: number;
  status: BatchStatus;
  createdAt: number;
  cancelledAt: number | null;
  finishedAt: number | null;
};

export type BatchJobList = {
  total: number;
  rows: JobRow[];
  available: boolean;
  complete: boolean;
  message: string | null;
};

export type BatchJobTab = "pending" | "completed" | "failed";

export type BatchDetail = BatchRow & {
  connection: string | null;
  queue: string | null;
  jobs: Record<BatchJobTab, BatchJobList>;
};

type BatchesHorizon = {
  baseUrl: string;
  pollInterval: number;
  status: HorizonStatus;
};

export type BatchesPageProps = {
  horizon: BatchesHorizon;
  query: string;
  filters?: BatchFilterValues;
  finishedBatches?: number;
  batches: {
    data: BatchRow[];
    available: boolean;
    message: string | null;
  };
};

export type BatchDetailPageProps = {
  horizon: BatchesHorizon;
  batch: BatchDetail;
};
