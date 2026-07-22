import type { HorizonStatus } from "@/types/dashboard";

export type JobListType = "pending" | "completed" | "silenced";

export type JobRow = {
  id: string;
  index: number;
  name: string;
  shortName: string;
  connection: string;
  queue: string;
  status: string;
  tags: string[];
  attempts: number;
  retryOf: string | null;
  delay: number | null;
  pushedAt: number | null;
  reservedAt: number | null;
  completedAt: number | null;
  failedAt: number | null;
  runtime: number | null;
  occurredAt: number | null;
  retried: boolean;
  retryCompleted: boolean;
  retryCount: number;
  latestRetryStatus: string | null;
  retryEligible: boolean;
};

export type JobCollection = {
  data: JobRow[];
  total: number;
  available: boolean;
  message: string | null;
};

export type JobDetail = Omit<
  JobRow,
  | "index"
  | "occurredAt"
  | "retried"
  | "retryCompleted"
  | "retryCount"
  | "latestRetryStatus"
  | "retryEligible"
> & {
  delayedUntil: number | null;
  batchId: string | null;
  payload: Record<string, unknown>;
};

export type FailedJobRetry = {
  id: string;
  status: string;
  retriedAt: number | null;
};

export type FailedJobDetail = Omit<JobDetail, "completedAt"> & {
  retried: boolean;
  retriedBy: FailedJobRetry[];
  retryEligible: boolean;
  context: Record<string, unknown>;
  exception: string;
};

export type FailedJobsPageProps = {
  horizon: JobsPageProps["horizon"];
  query: string;
  jobs: JobCollection;
};

export type FailedJobDetailPageProps = {
  horizon: JobsPageProps["horizon"];
  job: FailedJobDetail;
};

export type JobsPageProps = {
  horizon: {
    baseUrl: string;
    pollInterval: number;
    status: HorizonStatus;
  };
  type: JobListType;
  jobs: JobCollection;
};

export type JobDetailPageProps = {
  horizon: JobsPageProps["horizon"];
  type: JobListType;
  job: JobDetail;
};
