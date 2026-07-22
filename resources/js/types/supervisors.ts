import type { HorizonPageProps } from "@/types/page";

export type SupervisorWarning = {
  title: string;
  description: string;
};

export type SupervisorDetails = {
  id: string;
  name: string;
  master: string;
  pid: number | null;
  status: string;
  connection: string;
  queues: string[];
  processes: number;
  balance: string | null;
  autoScalingStrategy: string | null;
  minProcesses: number | null;
  maxProcesses: number | null;
  balanceCooldown: number | null;
  balanceMaxShift: number | null;
  memory: number | null;
  timeout: number | null;
  retryAfter: number | null;
  maxTries: number | null;
  backoff: number | null;
  maxJobs: number | null;
  maxTime: number | null;
  sleep: number | null;
  rest: number | null;
  force: boolean | null;
  nice: number | null;
  warnings: SupervisorWarning[];
};

export type SupervisorDetailsPageProps = Pick<HorizonPageProps, "horizon"> & {
  supervisorDetails: {
    available: boolean;
    supervisor: SupervisorDetails | null;
    message: string | null;
  };
};
