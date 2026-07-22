import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";

import Dashboard from "@/pages/dashboard";
import { TooltipProvider } from "@/components/ui/tooltip";
import type { DashboardPageProps, DashboardSummary } from "@/types/dashboard";

vi.mock("@inertiajs/react", () => ({
  Head: () => null,
  Link: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
  router: { reload: vi.fn() },
  usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
}));

const summary: DashboardSummary = {
  available: true,
  status: "running",
  failedJobs: 1,
  completedJobs: 11,
  pendingJobs: 0,
  pendingReserved: 0,
  pendingReadyNow: 0,
  pendingDelayed: 0,
  failedJobsPerMinute: 0.02,
  failedJobsPastHour: 1,
  failedJobsPastDay: 1,
  failedRetentionMinutes: 10_080,
  completedJobsPerMinute: 0.18,
  completedJobsPastHour: 11,
  completedJobsPastDay: 11,
  completedRetentionMinutes: 60,
  activeBatches: 0,
  batchPreviews: [],
  processes: 1,
  waits: { "redis:default": 1 },
  maxWaitQueue: "redis:default",
  maxWaitSeconds: 1,
  queueWithMaxRuntime: "default",
  queueWithMaxThroughput: "default",
  message: null,
};

const props: DashboardPageProps = {
  horizon: { baseUrl: "/horizon", pollInterval: 0, status: "running" },
  summary,
  workload: {
    available: true,
    items: [
      {
        name: "default",
        connection: "redis",
        length: 2,
        wait: 1,
        processes: 1,
        paused: false,
        pausedUntil: null,
        splitQueues: null,
      },
    ],
    message: null,
  },
};

describe("Dashboard page", () => {
  it("renders the complete server response without a client-side cache or deferred fallback", () => {
    render(
      <TooltipProvider>
        <Dashboard {...props} />
      </TooltipProvider>,
    );

    expect(screen.getByText("Pending Jobs")).toBeVisible();
    expect(screen.getAllByText("default").length).toBeGreaterThan(0);
    expect(screen.queryByText("Instances")).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Loading workload")).not.toBeInTheDocument();
  });
});
