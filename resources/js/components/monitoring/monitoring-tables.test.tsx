import { fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { MonitoredTagsTable } from "@/components/monitoring/monitored-tags-table";
import { MonitoringJobTable } from "@/components/monitoring/monitoring-job-table";
import { TooltipProvider } from "@/components/ui/tooltip";
import type { JobRow } from "@/types/jobs";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

const job: JobRow = {
  id: "pending-1",
  index: 0,
  name: "App\\Jobs\\DelayedExport",
  shortName: "DelayedExport",
  connection: "redis",
  queue: "exports",
  status: "pending",
  tags: ["checkout"],
  attempts: 0,
  retryOf: null,
  delay: 60,
  scheduledAt: 1_784_281_060,
  originalScheduledAt: 1_784_281_060,
  pushedAt: 1_784_281_000,
  reservedAt: null,
  completedAt: null,
  failedAt: null,
  runtime: null,
  occurredAt: 1_784_281_000,
  retried: false,
  retryCompleted: false,
  retryCount: 0,
  latestRetryStatus: null,
  retryEligible: false,
};

describe("monitoring tables", () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it("sorts tags and uses encoded dedicated routes", () => {
    render(
      <TooltipProvider>
        <MonitoredTagsTable
          tags={[
            {
              tag: "checkout",
              trackedCount: 8,
              failedCount: 1,
              lastActivityAt: 1_784_281_000,
              silenced: false,
            },
            {
              tag: "App\\Models\\Order:1043",
              trackedCount: 3,
              failedCount: 0,
              lastActivityAt: 1_784_280_000,
              silenced: true,
            },
          ]}
          horizonBaseUrl="/horizon"
        />
      </TooltipProvider>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Sort by Tracked jobs ascending" }));
    const rows = within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row");

    expect(rows[0]).toHaveTextContent("App\\Models\\Order:1043");
    expect(screen.getByRole("link", { name: "App\\Models\\Order:1043" })).toHaveAttribute(
      "href",
      "/horizon/monitoring/App%5CModels%5COrder%3A1043/jobs",
    );
    expect(screen.getByText("Silenced")).toBeVisible();
    expect(screen.getByRole("button", { name: "Monitoring actions for checkout" })).toBeEnabled();
    expect(screen.getByRole("link", { name: "8" })).toHaveAttribute(
      "href",
      "/horizon/monitoring/checkout/jobs",
    );
    expect(screen.getByRole("link", { name: "1" })).toHaveAttribute(
      "href",
      "/horizon/monitoring/checkout/failed",
    );

    fireEvent.pointerDown(screen.getByRole("button", { name: "Monitoring actions for checkout" }), {
      button: 0,
      ctrlKey: false,
    });
    expect(screen.getByRole("menuitem", { name: "Retry failed jobs" })).toBeInTheDocument();
    expect(screen.getByRole("menuitem", { name: "Clear recent jobs" })).toBeInTheDocument();
  });

  it("shows delayed recent jobs with the correct column set", () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-07-17T09:36:40Z"));

    render(<MonitoringJobTable jobs={[job]} status="jobs" horizonBaseUrl="/horizon" />);

    expect(screen.getAllByRole("columnheader")).toHaveLength(3);
    expect(screen.getByRole("columnheader", { name: /runtime/i })).toBeVisible();
    expect(screen.queryByRole("columnheader", { name: /completed/i })).not.toBeInTheDocument();
    expect(screen.getByText("Delayed")).toBeVisible();
    expect(screen.getByRole("link", { name: "DelayedExport" })).toHaveAttribute(
      "href",
      "/horizon/jobs/pending/pending-1",
    );
    expect(screen.getByRole("button", { name: "Monitor checkout" })).toBeVisible();
  });

  it("uses the Monitoring navigation icon for the monitored-tags empty state", () => {
    render(
      <TooltipProvider>
        <MonitoredTagsTable tags={[]} horizonBaseUrl="/horizon" />
      </TooltipProvider>,
    );

    const row = screen.getByRole("row", { name: "No monitored tags" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass("lucide-search");
    expect(row).toHaveTextContent("exact tag matches");
  });

  it("uses the Monitoring navigation icon for tag-job empty states", () => {
    render(<MonitoringJobTable jobs={[]} status="jobs" horizonBaseUrl="/horizon" />);

    const row = screen.getByRole("row", { name: "No jobs for this tag" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass("lucide-search");
  });
});
