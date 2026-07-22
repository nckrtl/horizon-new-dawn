import { fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import BatchesIndex from "@/pages/batches/index";
import FailedJobsIndex from "@/pages/failed-jobs/index";
import MonitoringIndex from "@/pages/monitoring/index";
import { CardHeader, CardTitle } from "@/components/ui/card";

const inertia = vi.hoisted(() => ({
  pollStart: vi.fn(),
  pollStop: vi.fn(),
  routerGet: vi.fn(),
  usePoll: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
  Head: () => null,
  InfiniteScroll: ({ children }: { children: ReactNode }) => children,
  Link: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
  router: { delete: vi.fn(), get: inertia.routerGet, post: vi.fn(), reload: vi.fn() },
  usePage: () => ({ props: { navigationCounts: undefined } }),
  usePoll: inertia.usePoll,
  useForm: () => ({
    clearErrors: vi.fn(),
    data: { tag: "" },
    errors: {},
    post: vi.fn(),
    processing: false,
    reset: vi.fn(),
    setData: vi.fn(),
  }),
}));

vi.mock("@/layouts/horizon-layout", () => ({
  useAutoLoadPreference: () => ({ autoLoad: true }),
  useResolvedNavigationCounts: () => undefined,
}));

const horizon = { baseUrl: "/horizon", pollInterval: 0, status: "running" as const };

describe("Inertia search pages", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    inertia.pollStart.mockReset();
    inertia.pollStop.mockReset();
    inertia.routerGet.mockReset();
    inertia.usePoll.mockReset();
    inertia.usePoll.mockReturnValue({ start: inertia.pollStart, stop: inertia.pollStop });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("debounces full failed-job tag searches and resets scroll state", () => {
    render(
      <FailedJobsIndex
        horizon={horizon}
        query=""
        jobs={{ data: [], total: 0, retryable: false, available: true, message: null }}
      />,
    );

    fireEvent.change(screen.getByRole("searchbox", { name: "Filter failed jobs by exact tag" }), {
      target: { value: "tenant:42" },
    });
    vi.advanceTimersByTime(499);
    expect(inertia.routerGet).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);

    expect(inertia.routerGet).toHaveBeenCalledWith(
      "/horizon/failed?tag=tenant%3A42",
      {},
      expect.objectContaining({ reset: ["jobs"], replace: true }),
    );
  });

  it("debounces batch searches through the handoff search field", () => {
    render(
      <BatchesIndex
        horizon={horizon}
        query="imports"
        batches={{ data: [], available: true, message: null }}
      />,
    );

    fireEvent.change(screen.getByLabelText("Search batches by name or ID"), {
      target: { value: "" },
    });
    vi.advanceTimersByTime(500);

    expect(inertia.routerGet).toHaveBeenCalledWith(
      "/horizon/batches",
      {},
      expect.objectContaining({ reset: ["batches"], replace: true }),
    );
  });

  it("stops list polling while a server-backed search is waiting to settle", () => {
    const { unmount } = render(
      <FailedJobsIndex
        horizon={{ ...horizon, pollInterval: 5_000 }}
        query=""
        jobs={{ data: [], total: 0, retryable: false, available: true, message: null }}
      />,
    );

    inertia.pollStop.mockClear();
    fireEvent.change(screen.getByRole("searchbox", { name: "Filter failed jobs by exact tag" }), {
      target: { value: "tenant:42" },
    });

    expect(inertia.pollStop).toHaveBeenCalled();

    unmount();
    inertia.pollStop.mockClear();

    render(
      <BatchesIndex
        horizon={{ ...horizon, pollInterval: 5_000 }}
        query=""
        batches={{ data: [], available: true, message: null }}
      />,
    );

    inertia.pollStop.mockClear();
    fireEvent.change(screen.getByLabelText("Search batches by name or ID"), {
      target: { value: "imports" },
    });

    expect(inertia.pollStop).toHaveBeenCalled();
  });

  it("keeps action and plain card headers on the same 54px rhythm", () => {
    const { rerender } = render(
      <MonitoringIndex horizon={horizon} tags={{ data: [], available: true, message: null }} />,
    );

    const monitoringHeader = screen
      .getByRole("button", { name: "Monitor Tag" })
      .closest('[data-slot="card-header"]');

    expect(monitoringHeader).toHaveClass(
      "flex",
      "h-[54px]",
      "items-center",
      "justify-between",
      "gap-3",
      "px-6",
      "py-0",
    );

    rerender(
      <CardHeader>
        <CardTitle>Completed Jobs</CardTitle>
      </CardHeader>,
    );

    expect(screen.getByText("Completed Jobs").closest('[data-slot="card-header"]')).toHaveClass(
      "min-h-[54px]",
    );
  });
});
