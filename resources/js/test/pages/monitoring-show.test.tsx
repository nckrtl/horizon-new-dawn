import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import MonitoringShow from "@/pages/monitoring/show";
import type { JobRow } from "@/types/jobs";
import type { MonitoringTagPageProps } from "@/types/monitoring";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

const job = (overrides: Partial<JobRow>): JobRow => ({
  id: "job-1",
  index: 0,
  name: "App\\Jobs\\DelayedExport",
  shortName: "DelayedExport",
  connection: "redis",
  queue: "exports",
  status: "completed",
  tags: ["customer:42"],
  attempts: 1,
  retryOf: null,
  delay: null,
  scheduledAt: null,
  originalScheduledAt: null,
  pushedAt: 1_784_281_000,
  reservedAt: 1_784_281_001,
  completedAt: 1_784_281_002,
  failedAt: null,
  runtime: 1,
  occurredAt: 1_784_281_002,
  retried: false,
  retryCompleted: false,
  retryCount: 0,
  latestRetryStatus: null,
  retryEligible: false,
  ...overrides,
});

const props: MonitoringTagPageProps = {
  horizon: { baseUrl: "/horizon", pollInterval: 0, status: "running" },
  tag: "customer:42",
  status: "jobs",
  summary: {
    tag: "customer:42",
    trackedCount: 2,
    failedCount: 0,
    silenced: false,
    monitoredRetentionMinutes: 10_080,
    failedRetentionMinutes: 10_080,
  },
  jobs: {
    data: [
      job({}),
      job({
        id: "job-2",
        index: 1,
        name: "App\\Jobs\\SendInvoice",
        shortName: "SendInvoice",
        queue: "default",
      }),
    ],
    total: 2,
    available: true,
    message: null,
  },
};

describe("Monitoring tag page filters", () => {
  beforeEach(() => {
    window.history.replaceState(
      {},
      "",
      "/horizon/monitoring/customer%3A42/jobs?starting_at=0&job=App%5CJobs%5CDelayedExport&queue=exports&tab=jobs",
    );
  });

  it("restores filters from the URL and removes them when cleared", async () => {
    render(<MonitoringShow {...props} />);

    expect(screen.getByRole("button", { name: "Filter jobs, 2 active" })).toBeVisible();
    expect(screen.getByRole("link", { name: "DelayedExport" })).toBeVisible();
    expect(screen.queryByRole("link", { name: "SendInvoice" })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Filter jobs, 2 active" }));
    fireEvent.click(screen.getByRole("button", { name: "Clear filters" }));

    await waitFor(() => {
      expect(window.location.search).toBe("?starting_at=0&tab=jobs");
    });
    fireEvent.click(screen.getByRole("button", { name: "Done" }));
    expect(screen.getByRole("button", { name: "Filter jobs" })).toBeVisible();
    expect(screen.getByRole("link", { name: "SendInvoice" })).toBeVisible();
  });

  it("keeps active filters clearable when the selected status has no jobs", () => {
    window.history.replaceState(
      {},
      "",
      "/horizon/monitoring/customer%3A42/failed?job=App%5CJobs%5CDelayedExport&queue=exports&tab=failed",
    );

    render(
      <MonitoringShow {...props} status="failed" jobs={{ ...props.jobs, data: [], total: 0 }} />,
    );

    expect(screen.getByRole("button", { name: "Filter jobs, 2 active" })).toBeEnabled();
  });
});
