import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import Dashboard from "@/pages/dashboard";
import RunningInstancesIndex from "@/pages/instances/index";
import type { DashboardPageProps, RunningInstancesPageProps } from "@/types/dashboard";

const refresh = vi.hoisted(() => vi.fn());
const dashboardRefresh = vi.hoisted(() => vi.fn());
const table = vi.hoisted(() => vi.fn());
const preference = vi.hoisted(() => ({ autoLoad: false }));

vi.mock("@inertiajs/react", () => ({ Head: () => null }));
vi.mock("@/hooks/use-dashboard-refresh", () => ({
  useDashboardRefresh: dashboardRefresh,
  usePageRefresh: refresh,
}));
vi.mock("@/layouts/horizon-layout", () => ({
  useAutoLoadPreference: () => ({ autoLoad: preference.autoLoad }),
}));
vi.mock("@/components/dashboard/dashboard-overview", () => ({
  DashboardOverview: () => null,
}));
vi.mock("@/components/dashboard/workload-table", () => ({
  WorkloadTable: () => null,
}));
vi.mock("@/components/dashboard/supervisors-table", () => ({
  SupervisorsTable: (props: {
    onInstanceTransition: (instance: string, transition: "pausing" | "continuing") => void;
    onSupervisorTransition: (supervisor: string, transition: "pausing" | "continuing") => void;
    transitions: {
      instances: Record<string, string>;
      supervisors: Record<string, string>;
    };
  }) => {
    table(props);

    return (
      <>
        <button
          type="button"
          onClick={() => props.onInstanceTransition("local-host-a1b2", "pausing")}
        >
          Begin instance pause
        </button>
        <button
          type="button"
          onClick={() => props.onSupervisorTransition("local-host-a1b2:supervisor-1", "continuing")}
        >
          Begin supervisor continue
        </button>
      </>
    );
  },
}));

function pageProps(
  masterStatus: string,
  supervisorStatus: string,
  pollInterval = 5_000,
): RunningInstancesPageProps {
  return {
    horizon: {
      baseUrl: "/horizon",
      pollInterval,
      status: masterStatus === "paused" ? "paused" : "running",
    },
    supervisors: {
      available: true,
      message: null,
      groups: [
        {
          name: "local-host-a1b2",
          status: masterStatus,
          local: true,
          items: [
            {
              id: "local-host-a1b2:supervisor-1",
              name: "supervisor-1",
              connection: "redis",
              queues: ["default"],
              processes: 1,
              balancing: "Auto",
              status: supervisorStatus,
            },
          ],
        },
      ],
    },
  };
}

function dashboardProps(masterStatus: string, supervisorStatus: string): DashboardPageProps {
  return {
    ...pageProps(masterStatus, supervisorStatus),
    summary: {} as DashboardPageProps["summary"],
    workload: {
      available: true,
      items: [],
      message: null,
    },
  };
}

describe("instances process transition refresh ownership", () => {
  beforeEach(() => {
    preference.autoLoad = false;
    refresh.mockReset();
    dashboardRefresh.mockReset();
    table.mockReset();
  });

  it("temporarily enables the existing page poll until a master and its children converge", async () => {
    const view = render(<RunningInstancesIndex {...pageProps("running", "running")} />);

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], false);

    fireEvent.click(screen.getByRole("button", { name: "Begin instance pause" }));

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], true);
    expect(table.mock.lastCall?.[0].transitions.instances).toEqual({
      "local-host-a1b2": "pausing",
    });

    view.rerender(<RunningInstancesIndex {...pageProps("paused", "running")} />);

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], true);
    expect(table.mock.lastCall?.[0].transitions.instances).toEqual({
      "local-host-a1b2": "pausing",
    });

    view.rerender(<RunningInstancesIndex {...pageProps("paused", "paused")} />);

    await waitFor(() => {
      expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], false);
      expect(table.mock.lastCall?.[0].transitions.instances).toEqual({});
    });
  });

  it("keeps polling through an unexpected intermediate process status", async () => {
    const view = render(<RunningInstancesIndex {...pageProps("running", "running")} />);

    fireEvent.click(screen.getByRole("button", { name: "Begin instance pause" }));
    view.rerender(<RunningInstancesIndex {...pageProps("inactive", "running")} />);

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], true);
    expect(table.mock.lastCall?.[0].transitions.instances).toEqual({
      "local-host-a1b2": "pausing",
    });

    view.rerender(<RunningInstancesIndex {...pageProps("paused", "paused")} />);

    await waitFor(() => {
      expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], false);
      expect(table.mock.lastCall?.[0].transitions.instances).toEqual({});
    });
  });

  it("keeps the same poll enabled while automatic refresh already owns it", () => {
    preference.autoLoad = true;

    render(<RunningInstancesIndex {...pageProps("paused", "paused")} />);
    fireEvent.click(screen.getByRole("button", { name: "Begin supervisor continue" }));

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], true);
    expect(table.mock.lastCall?.[0].transitions.supervisors).toEqual({
      "local-host-a1b2:supervisor-1": "continuing",
    });
  });

  it("uses the package poll cadence for transitions when configured polling is disabled", () => {
    render(<RunningInstancesIndex {...pageProps("running", "running", 0)} />);

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], false);

    fireEvent.click(screen.getByRole("button", { name: "Begin instance pause" }));

    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], true);
  });

  it("allows an independently continued child after the parent pause converges", async () => {
    const view = render(<RunningInstancesIndex {...pageProps("running", "running")} />);

    fireEvent.click(screen.getByRole("button", { name: "Begin instance pause" }));
    view.rerender(<RunningInstancesIndex {...pageProps("paused", "paused")} />);

    await waitFor(() => {
      expect(table.mock.lastCall?.[0].transitions.instances).toEqual({});
    });

    fireEvent.click(screen.getByRole("button", { name: "Begin supervisor continue" }));

    expect(table.mock.lastCall?.[0].transitions.supervisors).toEqual({
      "local-host-a1b2:supervisor-1": "continuing",
    });
    expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], true);

    view.rerender(<RunningInstancesIndex {...pageProps("paused", "running")} />);

    await waitFor(() => {
      expect(table.mock.lastCall?.[0].transitions.supervisors).toEqual({});
      expect(refresh).toHaveBeenLastCalledWith(5_000, ["supervisors"], false);
    });
  });

  it("uses the Dashboard's existing poll for the same transition lifecycle", async () => {
    const view = render(<Dashboard {...dashboardProps("running", "running")} />);

    expect(dashboardRefresh).toHaveBeenLastCalledWith(5_000, false);

    fireEvent.click(screen.getByRole("button", { name: "Begin instance pause" }));

    expect(dashboardRefresh).toHaveBeenLastCalledWith(5_000, true);
    expect(table.mock.lastCall?.[0].transitions.instances).toEqual({
      "local-host-a1b2": "pausing",
    });

    view.rerender(<Dashboard {...dashboardProps("paused", "paused")} />);

    await waitFor(() => {
      expect(dashboardRefresh).toHaveBeenLastCalledWith(5_000, false);
      expect(table.mock.lastCall?.[0].transitions.instances).toEqual({});
    });
  });
});
