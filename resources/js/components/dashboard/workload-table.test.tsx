import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { WorkloadTable } from "@/components/dashboard/workload-table";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

describe("WorkloadTable", () => {
  it("renders Horizon workload rows", () => {
    render(
      <WorkloadTable
        horizonBaseUrl="/horizon"
        workload={{
          available: true,
          message: null,
          items: [
            {
              name: "default",
              connection: "redis",
              length: 12,
              wait: 3,
              processes: 4,
              paused: false,
              pausedUntil: null,
              splitQueues: null,
            },
          ],
        }}
      />,
    );

    expect(screen.getByRole("cell", { name: "default" })).toBeVisible();
    expect(screen.getByRole("cell", { name: "12" })).toBeVisible();
    expect(screen.getByRole("cell", { name: "A few seconds" })).toBeVisible();
    expect(screen.getByRole("cell", { name: "4" })).toBeVisible();
  });

  it("distinguishes an empty queue from unavailable data", () => {
    const { rerender } = render(
      <WorkloadTable
        horizonBaseUrl="/horizon"
        workload={{ available: true, message: null, items: [] }}
      />,
    );

    expect(screen.getByText("All queues are clear")).toBeVisible();
    expect(document.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(
      "lucide-layout-dashboard",
    );

    rerender(
      <WorkloadTable
        horizonBaseUrl="/horizon"
        workload={{
          available: false,
          message: "Horizon workload is currently unavailable.",
          items: [],
        }}
      />,
    );

    expect(screen.getByText("Horizon workload is currently unavailable.")).toBeVisible();
  });

  it("sorts parent workload groups without detaching split queues", () => {
    render(
      <WorkloadTable
        horizonBaseUrl="/horizon"
        workload={{
          available: true,
          message: null,
          items: [
            {
              name: "zeta",
              connection: "redis",
              length: 2,
              wait: 3600,
              processes: 1,
              paused: false,
              pausedUntil: null,
              splitQueues: null,
            },
            {
              name: "alpha,beta",
              connection: "redis",
              length: 10,
              wait: 8,
              processes: 3,
              paused: false,
              pausedUntil: null,
              splitQueues: [
                { name: "alpha", length: 6, wait: 8, paused: false, pausedUntil: null },
                { name: "beta", length: 4, wait: 3, paused: true, pausedUntil: null },
              ],
            },
          ],
        }}
      />,
    );

    fireEvent.click(screen.getByRole("button", { name: "Sort by Ready Jobs ascending" }));
    const rowGroups = screen.getAllByRole("rowgroup");
    const rows = within(rowGroups[1]).getAllByRole("row");

    expect(rows[0]).toHaveTextContent("zeta");
    expect(rows[0]).toHaveTextContent("An hour");
    expect(rows[1]).toHaveTextContent("alpha, beta");
    expect(rows[2]).toHaveTextContent("alpha");
    expect(rows[3]).toHaveTextContent("beta");
    expect(screen.getAllByRole("button", { name: /queue actions/i })).toHaveLength(3);
    expect(screen.getByText("Paused")).toBeVisible();
  });
});
