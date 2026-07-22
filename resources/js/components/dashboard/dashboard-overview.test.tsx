import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { DashboardOverview } from "@/components/dashboard/dashboard-overview";
import { TooltipProvider } from "@/components/ui/tooltip";
import type { DashboardSummary } from "@/types/dashboard";

const summary: DashboardSummary = {
  available: true,
  status: "running",
  failedJobs: 6,
  completedJobs: 120,
  pendingJobs: 12,
  pendingReserved: 2,
  pendingReadyNow: 7,
  pendingDelayed: 3,
  failedJobsPerMinute: 0.1,
  failedJobsPastHour: 6,
  failedJobsPastDay: 18,
  failedRetentionMinutes: 10_080,
  completedJobsPerMinute: 1.25,
  completedJobsPastHour: 75,
  completedJobsPastDay: 982,
  completedRetentionMinutes: 60,
  activeBatches: 3,
  batchPreviews: [
    { id: "batch-3", name: "Archive audit logs", progress: 48 },
    { id: "batch-2", name: "Import order CSV", progress: 13 },
    { id: "batch-1", name: "Reindex product catalog", progress: 72 },
  ],
  processes: 8,
  waits: { "redis:default": 2 },
  maxWaitQueue: "redis:default",
  maxWaitSeconds: 2,
  queueWithMaxRuntime: "exports",
  queueWithMaxThroughput: "default",
  message: null,
};

const links = {
  pending: "/horizon/jobs/pending",
  failed: "/horizon/failed",
  completed: "/horizon/jobs/completed",
  batches: "/horizon/batches",
  batch: (id: string) => `/horizon/batches/${id}`,
};

describe("DashboardOverview", () => {
  it("renders the approved retained job populations and rolling statistics", () => {
    render(
      <TooltipProvider>
        <DashboardOverview summary={summary} links={links} />
      </TooltipProvider>,
    );

    const pending = screen.getByRole("link", { name: /Pending Jobs/ });
    const failed = screen.getByRole("link", { name: /Failed Jobs/ });
    const completed = screen.getByRole("link", { name: /Completed Jobs/ });

    expect(pending).toHaveAttribute("href", "/horizon/jobs/pending");
    expect(within(pending).getByText("12")).toBeVisible();
    expect(within(pending).getByText("Reserved")).toBeVisible();
    expect(within(pending).getByText("Ready")).toBeVisible();
    expect(within(pending).getByText("Delayed")).toBeVisible();
    expect(within(failed).getByText("Average/minute")).toBeVisible();
    expect(within(failed).getByText("0.1")).toBeVisible();
    expect(within(completed).getByText("1.25")).toBeVisible();
    expect(within(completed).getByText("75")).toBeVisible();
    expect(within(completed).getByText("982")).toBeVisible();
    expect(within(completed).getByText("Retained 1 hour")).toBeVisible();

    expect(screen.getByRole("link", { name: /Archive audit logs/ })).toHaveAttribute(
      "href",
      "/horizon/batches/batch-3",
    );
    expect(screen.getByRole("link", { name: /Import order CSV/ })).toHaveAttribute(
      "href",
      "/horizon/batches/batch-2",
    );
    expect(screen.getByRole("link", { name: /Reindex product catalog/ })).toHaveAttribute(
      "href",
      "/horizon/batches/batch-1",
    );
    expect(screen.getAllByRole("progressbar")).toHaveLength(3);
  });

  it("does not render batch progress when there are no batch previews", () => {
    render(
      <TooltipProvider>
        <DashboardOverview
          summary={{ ...summary, activeBatches: 0, batchPreviews: [] }}
          links={links}
        />
      </TooltipProvider>,
    );

    expect(screen.queryByRole("progressbar")).not.toBeInTheDocument();
  });

  it("surfaces summary failures", () => {
    render(
      <TooltipProvider>
        <DashboardOverview
          summary={{
            ...summary,
            available: false,
            status: "unavailable",
            message: "Horizon data is currently unavailable.",
          }}
          links={links}
        />
      </TooltipProvider>,
    );

    expect(screen.getByRole("alert")).toHaveTextContent("Horizon data is currently unavailable.");
  });
});
