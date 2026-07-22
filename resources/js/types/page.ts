import type { HorizonStatus } from "@/types/dashboard";

export type HorizonNavigation =
  | "dashboard"
  | "instances"
  | "queues"
  | "monitoring"
  | "metrics"
  | "batches"
  | "pending"
  | "completed"
  | "silenced"
  | "failed";

export type NavigationCounts = {
  instances: number | null;
  monitoring: number | null;
  metrics: number | null;
  queues: number | null;
  batches: number | null;
  pending: number | null;
  completed: number | null;
  silenced: number | null;
  failed: number | null;
};

export type HorizonPageProps = {
  [key: string]: unknown;
  horizon: {
    baseUrl: string;
    pollInterval: number;
    status: HorizonStatus;
    processing: boolean;
    maintenanceMode: boolean;
    capabilities?: {
      queuePausing: boolean;
    };
  };
  flash?: {
    success?: string | null;
    error?: string | null;
  };
  monitoredTags?: string[];
  navigationCounts?: NavigationCounts;
  meta: {
    title: string;
    activeNavigation: HorizonNavigation;
  };
};
