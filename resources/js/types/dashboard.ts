import type { QueueWaitThreshold } from "@/types/queues";

export type HorizonStatus = "running" | "paused" | "inactive" | "unavailable";

export type DashboardSummary = {
  available: boolean;
  status: HorizonStatus;
  failedJobs: number;
  completedJobs: number;
  pendingJobs: number;
  pendingReserved: number | null;
  pendingReadyNow: number | null;
  pendingDelayed: number | null;
  failedJobsPerMinute: number;
  failedJobsPastHour: number;
  failedJobsPastDay: number;
  failedRetentionMinutes: number;
  completedJobsPerMinute: number;
  completedJobsPastHour: number;
  completedJobsPastDay: number;
  completedRetentionMinutes: number;
  activeBatches: number;
  batchPreviews: Array<{
    id: string;
    name: string;
    progress: number;
  }>;
  processes: number;
  waits: Record<string, number>;
  maxWaitQueue: string | null;
  maxWaitSeconds: number;
  queueWithMaxRuntime: string | null;
  queueWithMaxThroughput: string | null;
  message: string | null;
};

export type WorkloadItem = {
  name: string;
  connection: string;
  length: number;
  wait: number;
  processes: number;
  paused: boolean;
  pausedUntil: number | null;
  waitThreshold?: QueueWaitThreshold;
  splitQueues: Array<{
    name: string;
    wait: number;
    length: number;
    paused: boolean;
    pausedUntil: number | null;
    waitThreshold?: QueueWaitThreshold;
  }> | null;
};

export type DashboardWorkload = {
  available: boolean;
  items: WorkloadItem[];
  message: string | null;
};

export type SupervisorItem = {
  id: string;
  name: string;
  connection: string;
  queues: string[];
  processes: number;
  balancing: string;
  status: string;
};

export type DashboardSupervisors = {
  available: boolean;
  groups: Array<{
    name: string;
    environment?: string | null;
    pid?: number | null;
    status: string;
    local: boolean;
    items: SupervisorItem[];
  }>;
  message: string | null;
};

export type FailurePreview = {
  id: string;
  name: string;
  queue: string;
  failedAt: number;
};

export type RecentFailures = {
  available: boolean;
  items: FailurePreview[];
  message: string | null;
};

export type DashboardPageProps = {
  horizon: {
    baseUrl: string;
    pollInterval: number;
    status: HorizonStatus;
    processing?: boolean;
    capabilities?: {
      queuePausing: boolean;
    };
  };
  summary: DashboardSummary;
  workload: DashboardWorkload;
  supervisors?: DashboardSupervisors;
};

export type RunningInstancesPageProps = {
  horizon: {
    baseUrl: string;
    pollInterval: number;
    status: HorizonStatus;
    processing?: boolean;
    capabilities?: {
      queuePausing: boolean;
    };
  };
  supervisors: DashboardSupervisors;
};
