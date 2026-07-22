import type { HorizonStatus } from "@/types/dashboard";

export type MetricType = "jobs" | "queues";

export type MetricRow = {
  name: string;
  throughput: number;
  runtime: number;
};

export type MetricSnapshot = {
  timestamp: number;
  throughput: number;
  runtime: number | null;
};

export type MetricPreview = {
  data: MetricSnapshot[];
  available: boolean;
  message: string | null;
};

type MetricsHorizon = {
  baseUrl: string;
  pollInterval: number;
  status: HorizonStatus;
};

export type MetricsPageProps = {
  horizon: MetricsHorizon;
  type: MetricType;
  metrics: {
    data: MetricRow[];
    available: boolean;
    message: string | null;
  };
};

export type MetricPreviewPageProps = {
  horizon: MetricsHorizon;
  type: MetricType;
  name: string;
  preview: MetricPreview;
};
