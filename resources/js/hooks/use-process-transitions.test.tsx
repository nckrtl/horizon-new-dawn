import { act, renderHook } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

import { useProcessTransitions } from "@/hooks/use-process-transitions";
import type { DashboardSupervisors } from "@/types/dashboard";

const toast = vi.hoisted(() => ({ error: vi.fn() }));

vi.mock("sonner", () => ({ toast }));

const supervisors: DashboardSupervisors = {
  available: true,
  message: null,
  groups: [
    {
      name: "horizon-web-01",
      status: "paused",
      local: true,
      items: [
        {
          id: "horizon-web-01:supervisor-1",
          name: "supervisor-1",
          connection: "redis",
          queues: ["default"],
          processes: 1,
          balancing: "Auto",
          status: "paused",
        },
        {
          id: "horizon-web-01:supervisor-2",
          name: "supervisor-2",
          connection: "redis",
          queues: ["mail"],
          processes: 1,
          balancing: "Auto",
          status: "paused",
        },
      ],
    },
  ],
};

describe("useProcessTransitions", () => {
  afterEach(() => {
    vi.useRealTimers();
    toast.error.mockReset();
  });

  it("restores server state when an accepted command does not converge", () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useProcessTransitions(supervisors, 5_000));

    act(() => {
      result.current.onSupervisorTransition("horizon-web-01:supervisor-1", "continuing");
    });

    expect(result.current.hasPendingTransitions).toBe(true);

    act(() => {
      vi.advanceTimersByTime(29_999);
    });

    expect(result.current.hasPendingTransitions).toBe(true);

    act(() => {
      vi.advanceTimersByTime(1);
    });

    expect(result.current.hasPendingTransitions).toBe(false);
    expect(result.current.transitions.supervisors).toEqual({});
    expect(toast.error).toHaveBeenCalledWith(
      "Horizon did not report the requested state. Showing the latest status.",
    );
  });

  it("acquires parent and child transitions atomically", () => {
    const { result } = renderHook(() => useProcessTransitions(supervisors, 5_000));

    let childToken: number | null = null;
    let parentToken: number | null = null;

    act(() => {
      childToken = result.current.onSupervisorTransition(
        "horizon-web-01:supervisor-1",
        "continuing",
      );
      parentToken = result.current.onInstanceTransition("horizon-web-01", "pausing");
    });

    expect(childToken).toBeTypeOf("number");
    expect(parentToken).toBeNull();
    expect(result.current.transitions).toEqual({
      instances: {},
      supervisors: {
        "horizon-web-01:supervisor-1": "continuing",
      },
    });
  });

  it("does not let a stale failure clear a replacement transition", () => {
    const { result } = renderHook(() => useProcessTransitions(supervisors, 5_000));
    let originalToken: number | null = null;
    let replacementToken: number | null = null;

    act(() => {
      originalToken = result.current.onSupervisorTransition(
        "horizon-web-01:supervisor-1",
        "continuing",
      );
    });
    act(() => {
      result.current.onSupervisorTransitionFailure("horizon-web-01:supervisor-1", originalToken!);
      replacementToken = result.current.onSupervisorTransition(
        "horizon-web-01:supervisor-1",
        "pausing",
      );
      result.current.onSupervisorTransitionFailure("horizon-web-01:supervisor-1", originalToken!);
    });

    expect(replacementToken).toBeTypeOf("number");
    expect(result.current.transitions.supervisors).toEqual({
      "horizon-web-01:supervisor-1": "pausing",
    });
  });

  it("expires each transition from its own start time", () => {
    vi.useFakeTimers();

    const { result } = renderHook(() => useProcessTransitions(supervisors, 5_000));

    act(() => {
      result.current.onSupervisorTransition("horizon-web-01:supervisor-1", "continuing");
    });
    act(() => {
      vi.advanceTimersByTime(20_000);
    });
    act(() => {
      result.current.onSupervisorTransition("horizon-web-01:supervisor-2", "continuing");
    });
    act(() => {
      vi.advanceTimersByTime(10_000);
    });

    expect(result.current.transitions.supervisors).toEqual({
      "horizon-web-01:supervisor-2": "continuing",
    });
    expect(toast.error).toHaveBeenCalledTimes(1);

    act(() => {
      vi.advanceTimersByTime(20_000);
    });

    expect(result.current.hasPendingTransitions).toBe(false);
    expect(toast.error).toHaveBeenCalledTimes(2);
  });
});
