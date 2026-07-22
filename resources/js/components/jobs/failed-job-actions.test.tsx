import { act, fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import {
  RetryAllFailedJobsButton,
  RetryFailedJobButton,
} from "@/components/jobs/failed-job-actions";
import { TooltipProvider } from "@/components/ui/tooltip";

const inertia = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock("@inertiajs/react", () => ({
  router: inertia,
}));

describe("failed job actions", () => {
  beforeEach(() => inertia.post.mockReset());

  it("posts a single retry through the dedicated route", () => {
    render(
      <TooltipProvider>
        <RetryFailedJobButton jobId="failed-1" horizonBaseUrl="/horizon" />
      </TooltipProvider>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Retry failed job" }));

    expect(inertia.post).toHaveBeenCalledWith(
      "/horizon/failed/failed-1/retry",
      {},
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("posts retry-all and exposes its working state", () => {
    render(<RetryAllFailedJobsButton horizonBaseUrl="/horizon" disabled={false} />);

    fireEvent.click(screen.getByRole("button", { name: "Retry all failed jobs" }));
    const options = inertia.post.mock.calls[0]?.[2];

    expect(inertia.post).toHaveBeenCalledWith(
      "/horizon/failed/retry-all",
      {},
      expect.objectContaining({ preserveScroll: true }),
    );

    act(() => options.onStart());
    expect(screen.getByRole("button", { name: "Retrying failed jobs" })).toBeDisabled();
  });
});
