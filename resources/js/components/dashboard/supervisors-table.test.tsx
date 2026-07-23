import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { SupervisorsTable } from "@/components/dashboard/supervisors-table";
import type { DashboardSupervisors } from "@/types/dashboard";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

describe("SupervisorsTable", () => {
  it("renders supervisor groups and sorts their rows", () => {
    render(
      <SupervisorsTable
        autoRefreshEnabled={false}
        horizonBaseUrl="/horizon"
        supervisors={{
          available: true,
          message: null,
          groups: [
            {
              name: "horizon-web-01",
              status: "running",
              local: true,
              items: [
                {
                  id: "horizon-web-01:supervisor-heavy",
                  name: "supervisor-heavy",
                  connection: "redis",
                  queues: ["default", "mail"],
                  processes: 10,
                  balancing: "Auto",
                  status: "paused",
                },
                {
                  id: "horizon-web-01:supervisor-light",
                  name: "supervisor-light",
                  connection: "redis",
                  queues: ["exports"],
                  processes: 2,
                  balancing: "Simple",
                  status: "running",
                },
              ],
            },
          ],
        }}
      />,
    );

    expect(screen.getByRole("heading", { name: "Instances" })).toBeVisible();
    expect(screen.getByRole("heading", { name: "horizon-web-01" })).toBeVisible();
    expect(screen.getAllByText("Running")).toHaveLength(2);
    expect(screen.getByText("Paused")).toBeVisible();
    fireEvent.click(screen.getByRole("button", { name: "Sort by Processes ascending" }));

    const rows = within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row");
    expect(rows[0]).toHaveTextContent("supervisor-light");
    expect(rows[0]).toHaveTextContent("Running");
    expect(rows[1]).toHaveTextContent("supervisor-heavy");
    expect(rows[1]).toHaveTextContent("Paused");
  });

  it("uses the Dashboard navigation icon for an empty supervisor group", () => {
    render(
      <SupervisorsTable
        autoRefreshEnabled={false}
        horizonBaseUrl="/horizon"
        supervisors={{
          available: true,
          message: null,
          groups: [{ name: "horizon-web-01", status: "running", local: false, items: [] }],
        }}
      />,
    );

    const row = screen.getByRole("row", { name: "No supervisors" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(
      "lucide-layout-dashboard",
    );
  });

  it("keeps predicted scaling animated until the process count reaches its target", () => {
    vi.useFakeTimers();

    const supervisors = (
      processes: number,
      state: "up" | "down" | "steady",
      targetProcesses: number,
    ): DashboardSupervisors => ({
      available: true,
      message: null,
      groups: [
        {
          name: "horizon-web-01",
          status: "running",
          local: true,
          items: [
            {
              id: "horizon-web-01:autoscale",
              name: "autoscale",
              connection: "redis",
              queues: ["default"],
              processes,
              balancing: "Auto",
              status: "running",
              scaling: {
                readyJobs: 10,
                state,
                strategy: "time",
                targetProcesses,
              },
            },
          ],
        },
      ],
    });
    const renderTable = (
      processes: number,
      state: "up" | "down" | "steady",
      targetProcesses: number,
    ) => (
      <SupervisorsTable
        autoRefreshEnabled
        horizonBaseUrl="/horizon"
        supervisors={supervisors(processes, state, targetProcesses)}
      />
    );
    const view = render(renderTable(6, "up", 7));

    const scalingUp = screen.getByRole("button", {
      name: /^Scaling up from 6 processes to 7 processes/,
    });

    expect(scalingUp).toHaveAttribute("data-scaling-filled", "1");

    act(() => vi.advanceTimersByTime(720));
    expect(scalingUp).toHaveAttribute("data-scaling-filled", "2");

    view.rerender(renderTable(6, "up", 7));

    for (const filledBlocks of [3, 4, 5, 1]) {
      act(() => vi.advanceTimersByTime(720));

      expect(scalingUp).toHaveAttribute("data-scaling-filled", String(filledBlocks));
    }

    view.rerender(renderTable(7, "steady", 7));

    expect(
      screen.getByRole("button", { name: /^Finishing scale up at 7 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "1");

    for (const filledBlocks of [2, 3, 4, 5]) {
      act(() => vi.advanceTimersByTime(720));

      expect(scalingUp).toHaveAttribute("data-scaling-filled", String(filledBlocks));
    }

    act(() => vi.advanceTimersByTime(720));

    expect(
      screen.getByRole("button", { name: /^Autoscaling idle at 7 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "0");

    view.rerender(renderTable(7, "down", 6));

    const scalingDown = screen.getByRole("button", {
      name: /^Scaling down from 7 processes to 6 processes/,
    });

    expect(scalingDown).toHaveAttribute("data-scaling-filled", "5");

    act(() => vi.advanceTimersByTime(720));
    expect(scalingDown).toHaveAttribute("data-scaling-filled", "4");

    for (const filledBlocks of [3, 2, 1, 5]) {
      act(() => vi.advanceTimersByTime(720));

      expect(scalingDown).toHaveAttribute("data-scaling-filled", String(filledBlocks));
    }

    view.rerender(renderTable(6, "steady", 6));

    expect(
      screen.getByRole("button", { name: /^Finishing scale down at 6 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "5");

    for (const filledBlocks of [4, 3, 2, 1]) {
      act(() => vi.advanceTimersByTime(720));

      expect(scalingDown).toHaveAttribute("data-scaling-filled", String(filledBlocks));
    }

    act(() => vi.advanceTimersByTime(720));

    const steady = screen.getByRole("button", { name: /^Autoscaling idle at 6 processes/ });
    const blocks = steady.querySelectorAll("[data-scaling-block]");

    expect(steady).toHaveAttribute("data-scaling-filled", "0");
    expect(blocks).toHaveLength(5);
    expect([...blocks].every((block) => block.classList.contains("animate-none"))).toBe(true);
    expect([...blocks].every((block) => block.classList.contains("transition-none"))).toBe(true);

    view.unmount();
    vi.useRealTimers();
  });

  it("finishes the active cycle before reversing scaling direction", () => {
    vi.useFakeTimers();

    const supervisors = (state: "up" | "down", targetProcesses: number): DashboardSupervisors => ({
      available: true,
      message: null,
      groups: [
        {
          name: "horizon-web-01",
          status: "running",
          local: true,
          items: [
            {
              id: "horizon-web-01:autoscale",
              name: "autoscale",
              connection: "redis",
              queues: ["default"],
              processes: 6,
              balancing: "Auto",
              status: "running",
              scaling: {
                readyJobs: 10,
                state,
                strategy: "time",
                targetProcesses,
              },
            },
          ],
        },
      ],
    });
    const renderTable = (state: "up" | "down", targetProcesses: number) => (
      <SupervisorsTable
        autoRefreshEnabled
        horizonBaseUrl="/horizon"
        supervisors={supervisors(state, targetProcesses)}
      />
    );
    const view = render(renderTable("up", 7));
    const indicator = screen.getByRole("button", {
      name: /^Scaling up from 6 processes to 7 processes/,
    });

    act(() => vi.advanceTimersByTime(720));
    expect(indicator).toHaveAttribute("data-scaling-filled", "2");

    view.rerender(renderTable("down", 5));

    expect(
      screen.getByRole("button", { name: /^Finishing scale up at 6 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "2");

    for (const filledBlocks of [3, 4, 5]) {
      act(() => vi.advanceTimersByTime(720));

      expect(indicator).toHaveAttribute("data-scaling-filled", String(filledBlocks));
    }

    act(() => vi.advanceTimersByTime(720));

    expect(
      screen.getByRole("button", { name: /^Scaling down from 6 processes to 5 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "5");

    act(() => vi.advanceTimersByTime(720));
    expect(indicator).toHaveAttribute("data-scaling-filled", "4");

    view.rerender(renderTable("up", 7));

    expect(
      screen.getByRole("button", { name: /^Finishing scale down at 6 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "4");

    for (const filledBlocks of [3, 2, 1]) {
      act(() => vi.advanceTimersByTime(720));

      expect(indicator).toHaveAttribute("data-scaling-filled", String(filledBlocks));
    }

    act(() => vi.advanceTimersByTime(720));

    expect(
      screen.getByRole("button", { name: /^Scaling up from 6 processes to 7 processes/ }),
    ).toHaveAttribute("data-scaling-filled", "1");

    view.unmount();
    vi.useRealTimers();
  });

  it("repeats an unchanged scaling prediction while its target is not reached", () => {
    vi.useFakeTimers();

    const supervisors = (): DashboardSupervisors => ({
      available: true,
      message: null,
      groups: [
        {
          name: "horizon-web-01",
          status: "running",
          local: true,
          items: [
            {
              id: "horizon-web-01:autoscale",
              name: "autoscale",
              connection: "redis",
              queues: ["default"],
              processes: 6,
              balancing: "Auto",
              status: "running",
              scaling: {
                readyJobs: 10,
                state: "up",
                strategy: "time",
                targetProcesses: 7,
              },
            },
          ],
        },
      ],
    });
    const renderTable = () => (
      <SupervisorsTable autoRefreshEnabled horizonBaseUrl="/horizon" supervisors={supervisors()} />
    );
    const view = render(renderTable());

    expect(screen.getByRole("button", { name: /^Scaling up/ })).toHaveAttribute(
      "data-scaling-filled",
      "1",
    );

    act(() => vi.advanceTimersByTime(3600));

    expect(screen.getByRole("button", { name: /^Scaling up/ })).toHaveAttribute(
      "data-scaling-filled",
      "1",
    );

    view.rerender(renderTable());

    expect(screen.getByRole("button", { name: /^Scaling up/ })).toHaveAttribute(
      "data-scaling-filled",
      "1",
    );

    view.unmount();
    vi.useRealTimers();
  });
});
