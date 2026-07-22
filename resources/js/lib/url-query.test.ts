import { beforeEach, describe, expect, it, vi } from "vitest";

import { replaceCurrentQuery } from "@/lib/url-query";

const inertia = vi.hoisted(() => ({
  cancelAll: vi.fn(),
  replace: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
  router: inertia,
}));

describe("replaceCurrentQuery", () => {
  beforeEach(() => {
    inertia.cancelAll.mockReset();
    inertia.replace.mockReset();
    window.history.replaceState({}, "", "/horizon/jobs/pending?queue=default");
  });

  it("cancels stale background work and replaces the Inertia page URL", () => {
    replaceCurrentQuery({ queue: "critical" });

    expect(inertia.cancelAll).toHaveBeenCalledWith({
      async: true,
      prefetch: false,
      sync: false,
    });
    expect(inertia.replace).toHaveBeenCalledWith({
      url: "/horizon/jobs/pending?queue=critical",
      preserveScroll: true,
      preserveState: true,
    });
  });

  it("does nothing when the query is already current", () => {
    replaceCurrentQuery({ queue: "default" });

    expect(inertia.cancelAll).not.toHaveBeenCalled();
    expect(inertia.replace).not.toHaveBeenCalled();
  });
});
