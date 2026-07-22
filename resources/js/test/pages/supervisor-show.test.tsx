import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";

import SupervisorShow from "@/pages/supervisors/show";
import type { SupervisorDetailsPageProps } from "@/types/supervisors";

const refresh = vi.hoisted(() => vi.fn());

vi.mock("@inertiajs/react", () => ({
  Head: () => null,
  Link: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

vi.mock("@/hooks/use-dashboard-refresh", () => ({
  usePageRefresh: refresh,
}));

vi.mock("@/layouts/horizon-layout", () => ({
  useAutoLoadPreference: () => ({ autoLoad: true }),
}));

const props: SupervisorDetailsPageProps = {
  horizon: {
    baseUrl: "/horizon",
    pollInterval: 5_000,
    status: "running",
    processing: true,
    maintenanceMode: false,
  },
  supervisorDetails: {
    available: true,
    message: null,
    supervisor: {
      id: "local-host-a1b2:supervisor-1",
      name: "supervisor-1",
      master: "local-host-a1b2",
      pid: 42,
      status: "running",
      connection: "redis",
      queues: ["default"],
      processes: 1,
      balance: "auto",
      autoScalingStrategy: "time",
      minProcesses: 1,
      maxProcesses: 5,
      balanceCooldown: 3,
      balanceMaxShift: 1,
      memory: 128,
      timeout: 60,
      retryAfter: 90,
      maxTries: 3,
      backoff: 0,
      maxJobs: 0,
      maxTime: 0,
      sleep: 3,
      rest: 0,
      force: false,
      nice: 0,
      warnings: [],
    },
  },
};

describe("Supervisor detail page", () => {
  it("polls the supervisor detail prop when automatic loading is enabled", () => {
    render(<SupervisorShow {...props} />);

    expect(screen.getByText("supervisor-1")).toBeVisible();
    expect(refresh).toHaveBeenCalledWith(5_000, ["supervisorDetails"], true);
  });
});
