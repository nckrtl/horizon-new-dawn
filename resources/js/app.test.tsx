import { beforeEach, describe, expect, it, vi } from "vitest";

const inertia = vi.hoisted(() => ({
  createInertiaApp: vi.fn(),
  cancelAll: vi.fn(),
  on: vi.fn(),
}));

vi.mock("@inertiajs/react", () => ({
  createInertiaApp: inertia.createInertiaApp,
  router: { cancelAll: inertia.cancelAll, on: inertia.on },
}));

vi.mock("react-dom/client", () => ({
  createRoot: vi.fn(),
}));

describe("Inertia application", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.resetModules();
  });

  it("uses the Horizon primary color for the progress bar", async () => {
    await import("@/app");

    expect(inertia.createInertiaApp).toHaveBeenCalledWith(
      expect.objectContaining({
        progress: {
          color: "var(--primary)",
        },
      }),
    );
  });

  it("registers the Horizon shell as Inertia's default persistent layout", async () => {
    await import("@/app");

    expect(inertia.createInertiaApp).toHaveBeenCalledWith(
      expect.objectContaining({
        layout: expect.any(Function),
      }),
    );
  });

  it("registers recovery for stale Vite page chunks", async () => {
    const addEventListener = vi.spyOn(window, "addEventListener");

    await import("@/app");

    expect(addEventListener).toHaveBeenCalledWith("vite:preloadError", expect.any(Function));
  });

  it("registers recovery for asset-version changes discovered by background requests", async () => {
    await import("@/app");

    expect(inertia.on).toHaveBeenCalledWith("location", expect.any(Function));
  });

  it("configures URL preservation and latest-visit-wins request coordination", async () => {
    await import("@/app");

    expect(inertia.createInertiaApp).toHaveBeenCalledWith(
      expect.objectContaining({
        defaults: {
          visitOptions: expect.any(Function),
        },
      }),
    );
    expect(inertia.on).toHaveBeenCalledWith("before", expect.any(Function));
  });
});
