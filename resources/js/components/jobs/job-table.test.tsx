import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { JobTable } from "@/components/jobs/job-table";
import type { JobRow } from "@/types/jobs";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

const jobs: JobRow[] = [
  {
    id: "job-slow",
    index: 0,
    name: "App\\Jobs\\SlowJob",
    shortName: "SlowJob",
    connection: "redis",
    queue: "default",
    status: "completed",
    tags: ["tenant:1"],
    attempts: 1,
    retryOf: null,
    delay: null,
    scheduledAt: null,
    originalScheduledAt: null,
    pushedAt: 1_784_281_000,
    reservedAt: 1_784_281_001,
    completedAt: 1_784_281_006,
    failedAt: null,
    runtime: 5,
    occurredAt: 1_784_281_006,
    retried: false,
    retryCompleted: false,
    retryCount: 0,
    latestRetryStatus: null,
    retryEligible: false,
  },
  {
    id: "job-fast",
    index: 1,
    name: "App\\Jobs\\FastJob",
    shortName: "FastJob",
    connection: "redis",
    queue: "mail",
    status: "completed",
    tags: [],
    attempts: 1,
    retryOf: null,
    delay: null,
    scheduledAt: null,
    originalScheduledAt: null,
    pushedAt: 1_784_282_000,
    reservedAt: 1_784_282_001,
    completedAt: 1_784_282_002,
    failedAt: null,
    runtime: 1,
    occurredAt: 1_784_282_002,
    retried: false,
    retryCompleted: false,
    retryCount: 0,
    latestRetryStatus: null,
    retryEligible: false,
  },
];

describe("JobTable", () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it("sorts normalized runtime values and links through the dedicated route", () => {
    render(<JobTable jobs={jobs} type="completed" horizonBaseUrl="/horizon" />);

    fireEvent.click(screen.getByRole("button", { name: "Sort by Runtime ascending" }));
    const rows = within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row");

    expect(rows[0]).toHaveTextContent("FastJob");
    expect(rows[1]).toHaveTextContent("SlowJob");
    expect(screen.getByRole("link", { name: "FastJob" })).toHaveAttribute(
      "href",
      "/horizon/jobs/completed/job-fast",
    );
  });

  it("uses the compact pending-job column set", () => {
    render(
      <JobTable
        jobs={[
          {
            ...jobs[0],
            id: "job-delayed",
            status: "pending",
            delay: 60,
            scheduledAt: 2_000_000_000,
            tags: ["tenant:1", "exports", "priority", "region:eu"],
          },
        ]}
        type="pending"
        horizonBaseUrl="/horizon"
      />,
    );

    expect(screen.getAllByRole("columnheader")).toHaveLength(4);
    expect(screen.getByRole("columnheader", { name: "State" })).toBeVisible();
    expect(screen.queryByRole("columnheader", { name: /runtime/i })).not.toBeInTheDocument();
    expect(screen.getByText("Delayed")).toBeVisible();
    expect(screen.getByText(/\+1 more/)).toBeVisible();
  });

  it("presents a scheduled job as released once its release time passes", () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-07-23T08:00:00Z"));
    const scheduledAt = Date.now() / 1000 + 5;

    const { rerender } = render(
      <JobTable
        jobs={[
          {
            ...jobs[0],
            id: "job-scheduled",
            status: "pending",
            delay: 5,
            scheduledAt,
          },
        ]}
        type="pending"
        horizonBaseUrl="/horizon"
      />,
    );

    expect(screen.getByText("Delayed")).toBeVisible();

    act(() => {
      vi.advanceTimersByTime(5_001);
    });

    expect(screen.getByText("Released")).toBeVisible();
    expect(screen.queryByText("Delayed")).not.toBeInTheDocument();

    rerender(
      <JobTable
        jobs={[
          {
            ...jobs[0],
            id: "job-scheduled",
            status: "reserved",
            delay: 0,
            scheduledAt,
          },
        ]}
        type="pending"
        horizonBaseUrl="/horizon"
      />,
    );

    expect(screen.getByText("Reserved")).toBeVisible();
    expect(screen.queryByText("Released")).not.toBeInTheDocument();
  });

  it("offers to insert entries discovered while auto-load is disabled", () => {
    const loadNewEntries = vi.fn();

    render(
      <JobTable
        jobs={jobs}
        type="completed"
        horizonBaseUrl="/horizon"
        hasNewEntries
        onLoadNewEntries={loadNewEntries}
      />,
    );

    fireEvent.click(screen.getByRole("link", { name: "Reload" }));

    expect(loadNewEntries).toHaveBeenCalledOnce();
  });

  it.each([
    ["pending", "lucide-circle-pause"],
    ["completed", "lucide-circle-check"],
    ["silenced", "lucide-bell-off"],
  ] as const)("uses the %s navigation icon for its empty state", (type, iconName) => {
    render(<JobTable jobs={[]} type={type} horizonBaseUrl="/horizon" />);

    const row = screen.getByRole("row", { name: `No ${type} jobs` });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(iconName);
  });

  it("supports batch-specific empty-state copy", () => {
    render(
      <JobTable
        jobs={[]}
        type="completed"
        horizonBaseUrl="/horizon"
        emptyTitle="No retained completed jobs"
        emptyDescription="Horizon has already trimmed these completed jobs."
      />,
    );

    expect(screen.getByRole("row", { name: "No retained completed jobs" })).toHaveTextContent(
      "Horizon has already trimmed these completed jobs.",
    );
  });
});
