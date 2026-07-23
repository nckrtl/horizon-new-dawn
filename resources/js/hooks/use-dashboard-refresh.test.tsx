import { render } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { useDashboardRefresh, usePageRefresh } from "@/hooks/use-dashboard-refresh";

const { reload, start, stop, usePoll } = vi.hoisted(() => ({
  reload: vi.fn(),
  start: vi.fn(),
  stop: vi.fn(),
  usePoll: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
  router: { reload },
  usePoll,
}));

function RefreshHarness({
  interval,
  enabled = true,
  props,
}: {
  interval: number;
  enabled?: boolean;
  props?: string[];
}) {
  useDashboardRefresh(interval, enabled, props);

  return null;
}

function PageRefreshHarness({ resetProps }: { resetProps?: string[] }) {
  usePageRefresh(5000, ["summary", "activity"], true, resetProps);

  return null;
}

describe("useDashboardRefresh", () => {
  beforeEach(() => {
    reload.mockReset();
    start.mockReset();
    stop.mockReset();
    usePoll.mockReset();
    usePoll.mockReturnValue({ start, stop });
  });

  it("polls dashboard props through Inertia at the configured interval", () => {
    render(<RefreshHarness interval={5000} />);

    expect(usePoll).toHaveBeenCalledWith(5000, expect.any(Function), {
      autoStart: false,
      mode: "cancel",
    });
    expect(start).toHaveBeenCalledOnce();
    expect(usePoll.mock.calls[0][1]()).toEqual({
      only: ["summary", "workload", "supervisors", "horizon", "navigationCounts"],
      preserveUrl: true,
      showProgress: false,
    });
  });

  it("refreshes an explicit detail-page prop", () => {
    render(<RefreshHarness interval={5000} props={["batch"]} />);

    expect(usePoll.mock.calls[0][1]()).toEqual({
      only: ["batch", "horizon", "navigationCounts"],
      preserveUrl: true,
      showProgress: false,
    });
  });

  it("resets selected infinite-scroll props during a page refresh", () => {
    render(<PageRefreshHarness resetProps={["activity"]} />);

    expect(usePoll.mock.calls[0][1]()).toEqual({
      only: ["summary", "activity", "horizon", "navigationCounts"],
      preserveUrl: true,
      reset: ["activity"],
      showProgress: false,
    });
  });

  it("does not auto-start when automatic refresh is disabled", () => {
    render(<RefreshHarness interval={5000} enabled={false} />);

    expect(usePoll).toHaveBeenCalledWith(5000, expect.any(Function), {
      autoStart: false,
      mode: "cancel",
    });
    expect(start).not.toHaveBeenCalled();
    expect(stop).toHaveBeenCalledOnce();
  });

  it("stops an active poll when automatic refresh is disabled", () => {
    const { rerender } = render(<RefreshHarness interval={5000} enabled />);

    expect(start).toHaveBeenCalledOnce();
    stop.mockClear();

    rerender(<RefreshHarness interval={5000} enabled={false} />);

    expect(start).toHaveBeenCalledOnce();
    expect(stop).toHaveBeenCalled();
  });

  it("refreshes immediately when automatic refresh is enabled", () => {
    const { rerender } = render(<RefreshHarness interval={5000} enabled={false} />);

    expect(reload).not.toHaveBeenCalled();

    rerender(<RefreshHarness interval={5000} enabled />);

    expect(reload).toHaveBeenCalledOnce();
    expect(reload).toHaveBeenCalledWith({
      only: ["summary", "workload", "supervisors", "horizon", "navigationCounts"],
      preserveUrl: true,
      showProgress: false,
    });
  });
});
