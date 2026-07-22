import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import BatchShow from "@/pages/batches/show";
import type { BatchDetail, BatchDetailPageProps, BatchJobList } from "@/types/batches";
import type { JobRow } from "@/types/jobs";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

function job(
  id: string,
  shortName: string,
  status: "pending" | "completed" | "failed",
  index: number,
): JobRow {
  return {
    id,
    index,
    name: `App\\Jobs\\${shortName}`,
    shortName,
    connection: "redis",
    queue: "imports",
    status,
    tags: [],
    attempts: 1,
    retryOf: null,
    delay: null,
    pushedAt: 1_784_281_000 + index,
    reservedAt: status === "pending" ? null : 1_784_281_001 + index,
    completedAt: status === "completed" ? 1_784_281_002 + index : null,
    failedAt: status === "failed" ? 1_784_281_002 + index : null,
    runtime: status === "pending" ? null : 1,
    occurredAt: 1_784_281_002 + index,
    retried: false,
    retryCompleted: false,
    retryCount: 0,
    latestRetryStatus: null,
    retryEligible: false,
  };
}

const pending = job("pending-1", "PendingImport", "pending", 0);
const completed = job("completed-1", "CompletedImport", "completed", 1);
const completedTwo = job("completed-2", "CompletedExport", "completed", 2);
const completedThree = job("completed-3", "CompletedReport", "completed", 3);
const failed = job("failed-1", "FailedImport", "failed", 4);

const props: BatchDetailPageProps = {
  horizon: { baseUrl: "/horizon", pollInterval: 5000, status: "running" },
  batch: {
    id: "batch-42",
    name: "Imports",
    displayName: "Imports",
    totalJobs: 5,
    pendingJobs: 2,
    failedJobs: 1,
    failedJobAttempts: 4,
    processedJobs: 3,
    progress: 60,
    status: "failures",
    createdAt: 1_784_281_000,
    cancelledAt: null,
    finishedAt: null,
    connection: "redis",
    queue: "imports",
    jobs: {
      pending: { total: 1, rows: [pending], available: true, complete: true, message: null },
      completed: {
        total: 3,
        rows: [completed, completedTwo, completedThree],
        available: true,
        complete: true,
        message: null,
      },
      failed: { total: 1, rows: [failed], available: true, complete: true, message: null },
    },
  },
};

function pageProps(
  batch: Partial<BatchDetail> = {},
  jobs: Partial<Record<"pending" | "completed" | "failed", BatchJobList>> = {},
): BatchDetailPageProps {
  return {
    ...props,
    batch: {
      ...props.batch,
      ...batch,
      jobs: { ...props.batch.jobs, ...jobs },
    },
  };
}

describe("BatchShow", () => {
  it("matches the handoff's compact batch detail rows", () => {
    render(<BatchShow {...props} />);

    expect(screen.getByText("Total jobs")).toBeVisible();
    expect(screen.getByText("Pending jobs")).toBeVisible();
    expect(screen.getByText("Failed job attempts")).toBeVisible();
    expect(screen.getByText("Failed job attempts").parentElement).toHaveTextContent("4");
    expect(screen.queryByText("Processed jobs")).not.toBeInTheDocument();
  });

  it("switches contextual job headers and scopes actions to the failed tab", () => {
    render(<BatchShow {...props} />);

    expect(screen.getByRole("tab", { name: "Pending 1" })).toBeVisible();
    expect(screen.getByRole("tab", { name: "Completed 3" })).toBeVisible();
    expect(screen.getByRole("tab", { name: "Failed 1" })).toHaveAttribute("aria-selected", "true");
    expect(screen.getByRole("heading", { name: "Failed Jobs" })).toBeVisible();
    expect(screen.getByRole("button", { name: "Failed batch jobs actions" })).toBeVisible();
    expect(screen.getByRole("link", { name: "FailedImport" })).toBeVisible();

    fireEvent.click(screen.getByRole("tab", { name: "Pending 1" }));
    expect(screen.getByRole("heading", { name: "Pending Jobs" })).toBeVisible();
    expect(screen.getByRole("link", { name: "PendingImport" })).toBeVisible();
    expect(
      screen.queryByRole("button", { name: "Failed batch jobs actions" }),
    ).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("tab", { name: "Completed 3" }));
    expect(screen.getByRole("heading", { name: "Completed Jobs" })).toBeVisible();
    expect(screen.getByRole("link", { name: "CompletedImport" })).toBeVisible();
  });

  it("defaults an active batch to pending jobs", () => {
    render(
      <BatchShow
        {...pageProps(
          {
            totalJobs: 4,
            failedJobs: 0,
            pendingJobs: 1,
            processedJobs: 3,
            progress: 75,
            status: "pending",
          },
          { failed: { total: 0, rows: [], available: true, complete: true, message: null } },
        )}
      />,
    );

    expect(screen.getByRole("tab", { name: "Pending 1" })).toHaveAttribute("aria-selected", "true");
  });

  it("defaults a successful batch to completed jobs", () => {
    render(
      <BatchShow
        {...pageProps(
          {
            totalJobs: 3,
            failedJobs: 0,
            pendingJobs: 0,
            processedJobs: 3,
            progress: 100,
            status: "finished",
          },
          {
            pending: { total: 0, rows: [], available: true, complete: true, message: null },
            failed: { total: 0, rows: [], available: true, complete: true, message: null },
          },
        )}
      />,
    );

    expect(screen.getByRole("tab", { name: "Completed 3" })).toHaveAttribute(
      "aria-selected",
      "true",
    );
  });

  it("uses a precise empty state without a duplicate retained-history warning", () => {
    render(
      <BatchShow
        {...pageProps(
          { failedJobs: 0, pendingJobs: 0, status: "finished" },
          {
            pending: { total: 0, rows: [], available: true, complete: true, message: null },
            completed: {
              total: 2,
              rows: [],
              available: true,
              complete: false,
              message: "Some completed jobs are no longer retained by Horizon.",
            },
            failed: { total: 0, rows: [], available: true, complete: true, message: null },
          },
        )}
      />,
    );

    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    expect(screen.getByRole("row", { name: "No retained completed jobs" })).toBeVisible();
  });
});
