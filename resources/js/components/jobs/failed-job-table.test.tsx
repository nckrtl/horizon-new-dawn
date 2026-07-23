import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { FailedJobTable } from "@/components/jobs/failed-job-table";
import { TooltipProvider } from "@/components/ui/tooltip";
import type { JobRow } from "@/types/jobs";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

const jobs: JobRow[] = [
  {
    id: "failed-slow",
    index: 0,
    name: "App\\Jobs\\SlowJob",
    shortName: "SlowJob",
    connection: "redis",
    queue: "payments",
    status: "failed",
    tags: ["order:42"],
    attempts: 3,
    retryOf: null,
    delay: null,
    scheduledAt: null,
    originalScheduledAt: null,
    pushedAt: 1_784_281_000,
    reservedAt: 1_784_281_001,
    completedAt: null,
    failedAt: 1_784_281_006,
    runtime: 5,
    occurredAt: 1_784_281_006,
    retried: true,
    retryCompleted: true,
    retryCount: 2,
    latestRetryStatus: "completed",
    retryEligible: false,
  },
  {
    id: "failed-fast",
    index: 1,
    name: "App\\Jobs\\FastJob",
    shortName: "FastJob",
    connection: "redis",
    queue: "emails",
    status: "failed",
    tags: [],
    attempts: 1,
    retryOf: "failed-original",
    delay: null,
    scheduledAt: null,
    originalScheduledAt: null,
    pushedAt: 1_784_282_000,
    reservedAt: 1_784_282_001,
    completedAt: null,
    failedAt: 1_784_282_002,
    runtime: 1,
    occurredAt: 1_784_282_002,
    retried: false,
    retryCompleted: false,
    retryCount: 0,
    latestRetryStatus: null,
    retryEligible: true,
  },
  {
    id: "failed-again",
    index: 2,
    name: "App\\Jobs\\RetryableJob",
    shortName: "RetryableJob",
    connection: "redis",
    queue: "payments",
    status: "failed",
    tags: [],
    attempts: 3,
    retryOf: null,
    delay: null,
    scheduledAt: null,
    originalScheduledAt: null,
    pushedAt: 1_784_283_000,
    reservedAt: 1_784_283_001,
    completedAt: null,
    failedAt: 1_784_283_004,
    runtime: 3,
    occurredAt: 1_784_283_004,
    retried: true,
    retryCompleted: false,
    retryCount: 2,
    latestRetryStatus: "failed",
    retryEligible: false,
  },
];

describe("FailedJobTable", () => {
  it("sorts rows and renders retry metadata through dedicated routes", () => {
    render(
      <TooltipProvider>
        <FailedJobTable jobs={jobs} horizonBaseUrl="/horizon" />
      </TooltipProvider>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Sort by Runtime ascending" }));
    const rows = within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row");

    expect(rows[0]).toHaveTextContent("FastJob");
    expect(rows[0]).toHaveTextContent("Retry of failed-original");
    expect(rows[1]).toHaveTextContent("Retried");
    expect(within(rows[1]).getByRole("button", { name: /Retried/ })).toHaveAttribute(
      "title",
      "Total retries: 2, Last retry status: Failed",
    );
    expect(within(rows[2]).getByRole("button", { name: /Retried/ })).toHaveAttribute(
      "title",
      "Total retries: 2, Last retry status: Completed",
    );
    expect(within(rows[0]).getByRole("button", { name: "Retry failed job" })).toBeEnabled();
    expect(within(rows[1]).queryByRole("button", { name: "Retry failed job" })).toBeNull();
    expect(within(rows[2]).queryByRole("button", { name: "Retry failed job" })).toBeNull();
    expect(screen.getByRole("link", { name: "FastJob" })).toHaveAttribute(
      "href",
      "/horizon/failed/failed-fast",
    );
  });

  it("uses the Failed Jobs navigation icon for its empty state", () => {
    render(
      <TooltipProvider>
        <FailedJobTable jobs={[]} horizonBaseUrl="/horizon" />
      </TooltipProvider>,
    );

    const row = screen.getByRole("row", { name: "No failed jobs" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass("lucide-circle-alert");
  });
});
