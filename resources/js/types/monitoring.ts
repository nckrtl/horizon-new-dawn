import type { HorizonStatus } from "@/types/dashboard";
import type { JobCollection } from "@/types/jobs";

export type MonitoringStatus = "jobs" | "failed";

export type MonitoredTag = {
  tag: string;
  trackedCount: number;
  failedCount: number;
  lastActivityAt: number | null;
  silenced: boolean;
};

export type MonitoredTagCollection = {
  data: MonitoredTag[];
  available: boolean;
  message: string | null;
};

export type MonitoringTagSummary = {
  tag: string;
  trackedCount: number;
  failedCount: number;
  silenced: boolean;
  monitoredRetentionMinutes: number;
  failedRetentionMinutes: number;
};

type MonitoringHorizon = {
  baseUrl: string;
  pollInterval: number;
  status: HorizonStatus;
};

export type MonitoringPageProps = {
  horizon: MonitoringHorizon;
  tags: MonitoredTagCollection;
};

export type MonitoringTagPageProps = {
  horizon: MonitoringHorizon;
  tag: string;
  status: MonitoringStatus;
  summary: MonitoringTagSummary;
  jobs: JobCollection;
};
