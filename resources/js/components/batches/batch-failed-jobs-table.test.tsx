import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { BatchFailedJobsTable } from "@/components/batches/batch-failed-jobs-table";
import type { JobRow } from "@/types/jobs";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

const failedJob = (id: string, name: string, failedAt: number): JobRow => ({
  id,
  index: 0,
  name,
  shortName: name.split("\\").at(-1) ?? name,
  connection: "redis",
  queue: "imports",
  status: "failed",
  tags: ["tenant:1"],
  attempts: 1,
  retryOf: null,
  delay: null,
  pushedAt: failedAt - 2,
  reservedAt: failedAt - 1,
  completedAt: null,
  failedAt,
  runtime: 1,
  occurredAt: failedAt,
  retried: false,
  retryCompleted: false,
  retryCount: 0,
  latestRetryStatus: null,
  retryEligible: false,
});

describe("BatchFailedJobsTable", () => {
  it("sorts the compact failed-job columns and links to failed job details", () => {
    render(
      <BatchFailedJobsTable
        jobs={[
          failedJob("failed-2", "App\\Jobs\\SlowExport", 1_784_282_000),
          failedJob("failed-1", "App\\Jobs\\FastImport", 1_784_281_000),
        ]}
        horizonBaseUrl="/horizon"
      />,
    );

    expect(screen.getAllByRole("columnheader")).toHaveLength(2);
    expect(screen.getByRole("link", { name: "FastImport" })).toHaveAttribute(
      "href",
      "/horizon/failed/failed-1",
    );
    expect(
      screen.queryByRole("button", { name: "Sort by Runtime ascending" }),
    ).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Sort by Failed At ascending" }));
    expect(within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row")[0]).toHaveTextContent(
      "FastImport",
    );
  });

  it("uses the Batches navigation icon for its empty state", () => {
    render(<BatchFailedJobsTable jobs={[]} horizonBaseUrl="/horizon" />);

    const row = screen.getByRole("row", { name: "No failed jobs" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass("lucide-layers3");
  });

  it("supports batch-retention empty-state copy", () => {
    render(
      <BatchFailedJobsTable
        jobs={[]}
        horizonBaseUrl="/horizon"
        emptyTitle="No retained failed jobs"
        emptyDescription="Horizon has already trimmed these failed jobs."
      />,
    );

    expect(screen.getByRole("row", { name: "No retained failed jobs" })).toHaveTextContent(
      "Horizon has already trimmed these failed jobs.",
    );
  });
});
