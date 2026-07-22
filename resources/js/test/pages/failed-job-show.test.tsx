import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import FailedJobShow from "@/pages/failed-jobs/show";
import type { FailedJobDetailPageProps } from "@/types/jobs";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

const props: FailedJobDetailPageProps = {
  horizon: { baseUrl: "/horizon", pollInterval: 0, status: "running" },
  job: {
    id: "failed-1",
    name: "App\\Jobs\\ImportFeed",
    shortName: "ImportFeed",
    connection: "redis",
    queue: "imports",
    status: "failed",
    tags: [],
    attempts: 2,
    retryOf: null,
    delay: null,
    delayedUntil: null,
    batchId: null,
    pushedAt: 1_784_281_000,
    reservedAt: 1_784_281_001,
    failedAt: 1_784_281_002,
    runtime: 1,
    retried: true,
    retriedBy: [{ id: "retry-1", status: "completed", retriedAt: 1_784_281_100 }],
    retryEligible: false,
    payload: {},
    context: {},
    exception: "Import failed",
  },
};

describe("FailedJobShow", () => {
  it("hides retry when the server marks the failed job ineligible", () => {
    render(<FailedJobShow {...props} />);

    expect(screen.queryByRole("button", { name: "Retry failed job" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Remove failed job" })).toBeEnabled();
  });

  it("offers retry for a failed retry leaf", () => {
    render(
      <FailedJobShow
        {...props}
        job={{
          ...props.job,
          retryOf: "cleared-parent",
          retried: false,
          retryEligible: true,
          retriedBy: [],
        }}
      />,
    );

    expect(screen.getByRole("button", { name: "Retry failed job" })).toBeEnabled();
  });
});
