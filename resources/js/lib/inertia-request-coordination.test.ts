import type { VisitOptions } from "@inertiajs/core";
import { beforeEach, describe, expect, it, vi } from "vitest";

import {
  backgroundVisitOptions,
  registerLatestInertiaVisitWins,
} from "@/lib/inertia-request-coordination";

const inertia = vi.hoisted(() => ({
  cancelAll: vi.fn(),
  on: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
  router: inertia,
}));

describe("Inertia request coordination", () => {
  beforeEach(() => {
    inertia.cancelAll.mockReset();
    inertia.on.mockReset();
  });

  it("preserves the current URL for background GET reloads", () => {
    const options = { async: true, method: "get", component: null } as VisitOptions;

    expect(backgroundVisitOptions("/horizon/queues/default", options)).toEqual({
      ...options,
      preserveUrl: true,
    });
  });

  it("leaves navigations, prefetches, and explicit URL ownership unchanged", () => {
    const navigation = { async: false, method: "get", component: null } as VisitOptions;
    const instantVisit = { async: true, method: "get", component: "Queues/Show" } as VisitOptions;
    const prefetch = {
      async: true,
      method: "get",
      component: null,
      prefetch: true,
    } as VisitOptions;
    const explicit = {
      async: true,
      method: "get",
      component: null,
      preserveUrl: false,
    } as VisitOptions;

    expect(backgroundVisitOptions("/horizon", navigation)).toBe(navigation);
    expect(backgroundVisitOptions("/horizon", instantVisit)).toBe(instantVisit);
    expect(backgroundVisitOptions("/horizon", prefetch)).toBe(prefetch);
    expect(backgroundVisitOptions("/horizon", explicit)).toBe(explicit);
  });

  it("cancels older async requests before a newer non-prefetch visit starts", () => {
    inertia.on.mockReturnValue(vi.fn());

    registerLatestInertiaVisitWins();

    expect(inertia.on).toHaveBeenCalledWith("before", expect.any(Function));

    const listener = inertia.on.mock.calls[0][1];
    listener({ detail: { visit: { prefetch: false } } });

    expect(inertia.cancelAll).toHaveBeenCalledWith({
      async: true,
      prefetch: false,
      sync: false,
    });
  });

  it("does not cancel active work for prefetch visits", () => {
    inertia.on.mockReturnValue(vi.fn());
    registerLatestInertiaVisitWins();

    const listener = inertia.on.mock.calls[0][1];
    listener({ detail: { visit: { prefetch: true } } });

    expect(inertia.cancelAll).not.toHaveBeenCalled();
  });

  it("rejects background visits while a foreground navigation is in flight", () => {
    inertia.on.mockReturnValue(vi.fn());
    registerLatestInertiaVisitWins();

    const listener = (name: string) =>
      inertia.on.mock.calls.find(([eventName]) => eventName === name)?.[1];

    listener("start")({ detail: { visit: { async: false } } });

    expect(listener("before")({ detail: { visit: { async: true, prefetch: false } } })).toBe(false);

    expect(
      listener("before")({
        detail: { visit: { async: true, deferredProps: true, prefetch: false } },
      }),
    ).toBeUndefined();

    listener("finish")({ detail: { visit: { async: false } } });

    expect(
      listener("before")({ detail: { visit: { async: true, prefetch: false } } }),
    ).toBeUndefined();
  });
});
