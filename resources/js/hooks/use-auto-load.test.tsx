import { act, renderHook } from "@testing-library/react";
import { mergeDataIntoQueryString } from "@inertiajs/core";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { useAutoLoad } from "@/hooks/use-auto-load";

const inertia = vi.hoisted(() => ({
  start: vi.fn(),
  stop: vi.fn(),
  usePoll: vi.fn(),
  scrollProps: {} as Record<string, unknown>,
}));

vi.mock("@inertiajs/react", () => ({
  usePoll: inertia.usePoll,
  usePage: () => ({ scrollProps: inertia.scrollProps }),
}));

describe("useAutoLoad", () => {
  beforeEach(() => {
    inertia.start.mockReset();
    inertia.stop.mockReset();
    inertia.usePoll.mockReset();
    inertia.usePoll.mockReturnValue({ start: inertia.start, stop: inertia.stop });
    inertia.scrollProps = {};
    window.history.replaceState({}, "", "/horizon/failed?starting_at=49&tag=tenant%3A42");
  });

  it("polls a fresh partial reload without carrying the scroll cursor", () => {
    renderHook(() => useAutoLoad({ enabled: true, prop: "jobs", interval: 1_000 }));

    expect(inertia.usePoll).toHaveBeenCalledWith(1_000, expect.any(Function), {
      autoStart: false,
      mode: "cancel",
    });
    const options = inertia.usePoll.mock.calls[0][1]();
    const [url] = mergeDataIntoQueryString("get", window.location.href, options.data);

    expect(url).toBe("http://localhost:3000/horizon/failed?tag=tenant%3A42");
    expect(options).toEqual({
      data: { starting_at: undefined },
      only: ["jobs", "horizon", "navigationCounts"],
      preserveUrl: true,
      reset: ["jobs"],
      showProgress: false,
    });
    expect(inertia.start).toHaveBeenCalledOnce();
  });

  it("polls only the active list while automatic refresh is disabled", () => {
    renderHook(() =>
      useAutoLoad({
        enabled: false,
        prop: "jobs",
        additionalProps: ["summary"],
        interval: 1_000,
      }),
    );

    expect(inertia.start).toHaveBeenCalledOnce();
    expect(inertia.usePoll.mock.calls[0][1]()).toEqual({
      data: { starting_at: undefined },
      only: ["jobs", "summary"],
      preserveUrl: true,
      reset: ["jobs"],
      showProgress: false,
    });
  });

  it("holds new rows until requested when automatic insertion is disabled", () => {
    const original = [{ id: "job-2" }, { id: "job-1" }];
    const refreshed = [{ id: "job-3" }, ...original];
    const { result, rerender } = renderHook(
      ({ items }) =>
        useAutoLoad({
          enabled: false,
          prop: "jobs",
          interval: 1_000,
          items,
        }),
      { initialProps: { items: original } },
    );

    rerender({ items: refreshed });

    expect(result.current).toMatchObject({
      items: original,
      hasNewEntries: true,
    });

    act(() => result.current.loadNewEntries());

    expect(result.current).toMatchObject({
      items: refreshed,
      hasNewEntries: false,
    });
  });

  it("keeps the loaded tail mounted when polling resets the first page", () => {
    inertia.scrollProps = {
      jobs: {
        pageName: "starting_at",
        previousPage: null,
        nextPage: 49,
        currentPage: -1,
        reset: true,
      },
    };
    const original = [
      { id: "job-4", status: "pending" },
      { id: "job-3", status: "pending" },
      { id: "job-2", status: "pending" },
      { id: "job-1", status: "pending" },
    ];
    const refreshed = [
      { id: "job-5", status: "pending" },
      { id: "job-4", status: "reserved" },
    ];
    const { result, rerender } = renderHook(
      ({ items }) =>
        useAutoLoad({
          enabled: true,
          prop: "jobs",
          interval: 1_000,
          items,
        }),
      { initialProps: { items: original } },
    );

    rerender({ items: refreshed });

    expect(result.current.items).toEqual([
      { id: "job-5", status: "pending" },
      { id: "job-4", status: "reserved" },
      { id: "job-3", status: "pending" },
      { id: "job-2", status: "pending" },
    ]);
  });

  it("holds a new reset prefix without shrinking the loaded tail", () => {
    inertia.scrollProps = {
      jobs: {
        pageName: "starting_at",
        previousPage: null,
        nextPage: 49,
        currentPage: -1,
        reset: true,
      },
    };
    const original = [
      { id: "job-4", status: "pending" },
      { id: "job-3", status: "pending" },
      { id: "job-2", status: "pending" },
      { id: "job-1", status: "pending" },
    ];
    const refreshed = [
      { id: "job-5", status: "pending" },
      { id: "job-4", status: "reserved" },
    ];
    const { result, rerender } = renderHook(
      ({ items }) =>
        useAutoLoad({
          enabled: false,
          prop: "jobs",
          interval: 1_000,
          items,
        }),
      { initialProps: { items: original } },
    );

    rerender({ items: refreshed });

    expect(result.current).toMatchObject({
      items: [
        { id: "job-4", status: "reserved" },
        { id: "job-3", status: "pending" },
        { id: "job-2", status: "pending" },
        { id: "job-1", status: "pending" },
      ],
      hasNewEntries: true,
    });
  });

  it("removes deleted rows while holding only a genuinely new prefix", () => {
    const original = [{ id: "job-2" }, { id: "job-1" }];
    const refreshed = [{ id: "job-3" }, { id: "job-1" }];
    const { result, rerender } = renderHook(
      ({ items }) =>
        useAutoLoad({
          enabled: false,
          prop: "jobs",
          interval: 1_000,
          items,
        }),
      { initialProps: { items: original } },
    );

    rerender({ items: refreshed });

    expect(result.current).toMatchObject({
      items: [{ id: "job-1" }],
      hasNewEntries: true,
    });
  });

  it("treats an empty or non-overlapping refresh as authoritative", () => {
    const original = [{ id: "job-2" }, { id: "job-1" }];
    const { result, rerender } = renderHook(
      ({ items }) =>
        useAutoLoad({
          enabled: false,
          prop: "jobs",
          interval: 1_000,
          items,
        }),
      { initialProps: { items: original } },
    );

    rerender({ items: [{ id: "job-4" }] });
    expect(result.current).toMatchObject({ items: [{ id: "job-4" }], hasNewEntries: false });

    rerender({ items: [] });
    expect(result.current).toMatchObject({ items: [], hasNewEntries: false });
  });

  it("replaces held rows when the list scope changes", () => {
    const original = [{ id: "failed-1" }];
    const searched = [{ id: "failed-42" }];
    const { result, rerender } = renderHook(
      ({ items, scope }) =>
        useAutoLoad({
          enabled: false,
          prop: "jobs",
          interval: 1_000,
          items,
          scope,
        }),
      { initialProps: { items: original, scope: "all" } },
    );

    rerender({ items: searched, scope: "tenant:42" });

    expect(result.current).toMatchObject({
      items: searched,
      hasNewEntries: false,
    });
  });

  it("stops native polling when polling is disabled or the hook unmounts", () => {
    const { unmount } = renderHook(() =>
      useAutoLoad({ enabled: true, prop: "jobs", interval: 1_000, polling: false }),
    );

    expect(inertia.start).not.toHaveBeenCalled();
    expect(inertia.stop).toHaveBeenCalledOnce();

    unmount();

    expect(inertia.stop).toHaveBeenCalledTimes(2);
  });

  it("removes a custom infinite-scroll cursor before replacing batches", () => {
    window.history.replaceState({}, "", "/horizon/batches?before_id=batch-50&query=import");
    renderHook(() =>
      useAutoLoad({
        enabled: true,
        prop: "batches",
        interval: 1_000,
        cursor: "before_id",
      }),
    );

    const options = inertia.usePoll.mock.calls[0][1]();
    const [url] = mergeDataIntoQueryString("get", window.location.href, options.data);

    expect(url).toBe("http://localhost:3000/horizon/batches?query=import");
    expect(options).toEqual(
      expect.objectContaining({
        data: { before_id: undefined },
        only: ["batches", "horizon", "navigationCounts"],
        reset: ["batches"],
      }),
    );
  });
});
