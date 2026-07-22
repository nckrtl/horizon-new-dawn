import { act, fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { BatchFailedJobsActions } from "@/components/batches/batch-actions";

const inertia = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock("@inertiajs/react", () => ({
  router: inertia,
}));

describe("BatchFailedJobsActions", () => {
  beforeEach(() => inertia.post.mockReset());

  it("offers batch-scoped retry and clear actions and exposes its working state", async () => {
    render(<BatchFailedJobsActions batchId="batch-1" horizonBaseUrl="/horizon" disabled={false} />);

    fireEvent.pointerDown(screen.getByRole("button", { name: "Failed batch jobs actions" }), {
      button: 0,
      ctrlKey: false,
    });
    expect(await screen.findByRole("menuitem", { name: "Clear all failed jobs" })).toBeVisible();
    fireEvent.click(screen.getByRole("menuitem", { name: "Retry all failed jobs" }));
    const options = inertia.post.mock.calls[0]?.[2];

    expect(inertia.post).toHaveBeenCalledWith(
      "/horizon/batches/batch-1/retry",
      {},
      expect.objectContaining({ preserveScroll: true }),
    );

    act(() => options.onStart());
    expect(screen.getByRole("button", { name: "Failed batch jobs actions" })).toBeDisabled();
  });
});
