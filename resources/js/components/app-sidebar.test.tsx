import { router } from "@inertiajs/react";
import { act, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { AppSidebar } from "@/components/app-sidebar";
import { SidebarProvider } from "@/components/ui/sidebar";
import { TooltipProvider } from "@/components/ui/tooltip";

const navigationLabels = [
  "Laravel Horizon",
  "Dashboard",
  "Monitoring",
  "Metrics",
  "Instances",
  "Queues",
  "Batches",
  "Jobs",
];

const navigationCounts = {
  instances: 1,
  monitoring: 2,
  metrics: 7,
  queues: 8,
  batches: 4,
  pending: 5,
  completed: 36,
  silenced: 3,
  failed: 6,
};

describe("AppSidebar", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(window, "innerWidth", {
      configurable: true,
      value: 1440,
    });
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockReturnValue({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      }),
    });
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("prefetches the dashboard on mount with stale-while-revalidate caching", () => {
    const prefetch = vi.spyOn(router, "prefetch").mockImplementation(() => undefined);

    render(
      <TooltipProvider>
        <SidebarProvider>
          <AppSidebar activeNavigation="dashboard" horizonBaseUrl="/horizon" />
        </SidebarProvider>
      </TooltipProvider>,
    );

    act(() => vi.advanceTimersByTime(100));

    expect(prefetch).toHaveBeenCalledOnce();
    expect(prefetch).toHaveBeenCalledWith(
      "/horizon",
      expect.objectContaining({ method: "get" }),
      expect.objectContaining({ cacheFor: ["5s", "5m"] }),
    );
  });

  it("prefetches every rendered navigation destination after hovering", () => {
    const prefetch = vi.spyOn(router, "prefetch").mockImplementation(() => undefined);

    render(
      <TooltipProvider>
        <SidebarProvider>
          <AppSidebar activeNavigation="dashboard" horizonBaseUrl="/horizon" />
        </SidebarProvider>
      </TooltipProvider>,
    );

    act(() => vi.advanceTimersByTime(100));
    prefetch.mockClear();

    navigationLabels.forEach((label, index) => {
      const link = screen.getByRole("link", { name: label });

      fireEvent.mouseEnter(link);
      act(() => vi.advanceTimersByTime(100));

      expect(prefetch).toHaveBeenCalledTimes(index + 1);
      expect(prefetch).toHaveBeenLastCalledWith(
        link.getAttribute("href"),
        expect.objectContaining({ method: "get" }),
        expect.objectContaining({
          cacheFor: label === "Laravel Horizon" || label === "Dashboard" ? ["5s", "5m"] : 30_000,
        }),
      );
    });
  });

  it("renders muted counters for every counted page", () => {
    render(
      <TooltipProvider>
        <SidebarProvider>
          <AppSidebar
            activeNavigation="dashboard"
            horizonBaseUrl="/horizon"
            navigationCounts={navigationCounts}
          />
        </SidebarProvider>
      </TooltipProvider>,
    );

    expect(screen.getByRole("link", { name: "Dashboard" }).parentElement).not.toHaveTextContent(
      /\d/,
    );
    [1, 2, 7, 8, 4, 50].forEach((count) => {
      const counter = screen.getByText(String(count));

      expect(counter).toBeVisible();
      expect(counter).toHaveAttribute("data-slot", "sidebar-menu-badge");
      expect(counter).not.toHaveAttribute("data-variant");
      expect(counter).toHaveClass("!top-1/2", "-translate-y-1/2", "!text-muted-foreground");
    });
  });

  it("shows zero counts and hides unavailable counts", () => {
    render(
      <TooltipProvider>
        <SidebarProvider>
          <AppSidebar
            activeNavigation="dashboard"
            horizonBaseUrl="/horizon"
            navigationCounts={{ ...navigationCounts, instances: 0, monitoring: null }}
          />
        </SidebarProvider>
      </TooltipProvider>,
    );

    expect(screen.getByRole("link", { name: "Monitoring" }).parentElement).not.toHaveTextContent(
      "2",
    );
    expect(screen.getByRole("link", { name: "Instances" }).parentElement).toHaveTextContent("0");
  });
});
