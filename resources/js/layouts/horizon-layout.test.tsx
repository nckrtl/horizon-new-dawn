import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import type { MouseEventHandler, ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { TooltipProvider } from "@/components/ui/tooltip";
import { HorizonLayout } from "@/layouts/horizon-layout";

const inertia = vi.hoisted(() => ({
  props: {
    horizon: {
      baseUrl: "/horizon",
      pollInterval: 5000,
      status: "running",
      processing: false,
      maintenanceMode: false,
    },
    navigationCounts: undefined as
      | {
          instances: number | null;
          monitoring: number | null;
          metrics: number | null;
          queues: number | null;
          batches: number | null;
          pending: number | null;
          completed: number | null;
          silenced: number | null;
          failed: number | null;
        }
      | undefined,
    meta: { title: "Dashboard", activeNavigation: "dashboard" },
  },
}));

function setViewportWidth(width: number) {
  Object.defineProperty(window, "innerWidth", {
    configurable: true,
    value: width,
  });
}

vi.mock("@inertiajs/react", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@inertiajs/react")>();

  return {
    ...actual,
    Link: ({
      children,
      href,
      onClick,
    }: {
      children: ReactNode;
      href: string;
      onClick?: MouseEventHandler<HTMLAnchorElement>;
    }) => (
      <a
        href={href}
        onClick={(event) => {
          event.preventDefault();
          onClick?.(event);
        }}
      >
        {children}
      </a>
    ),
    usePage: () => ({ props: inertia.props }),
  };
});

describe("HorizonLayout", () => {
  beforeEach(() => {
    localStorage.clear();
    setViewportWidth(1440);
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockReturnValue({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      }),
    });
    inertia.props.meta = { title: "Dashboard", activeNavigation: "dashboard" };
  });

  it("preserves shell controls while the Inertia page changes", () => {
    const { rerender } = render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    const autoLoad = screen.getByRole("button", { name: "Auto load new entries" });
    fireEvent.click(autoLoad);
    expect(autoLoad).toHaveAttribute("aria-pressed", "true");

    inertia.props.meta = { title: "Completed Jobs", activeNavigation: "completed" };
    rerender(
      <TooltipProvider>
        <HorizonLayout>
          <p>Completed jobs content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    expect(screen.getByText("Completed jobs content")).toBeVisible();
    expect(screen.queryByRole("heading", { name: "Completed Jobs" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Auto load new entries" })).toHaveAttribute(
      "aria-pressed",
      "true",
    );
  });

  it("retains resolved deferred navigation counts between page visits", async () => {
    const { rerender } = render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    inertia.props.navigationCounts = {
      instances: 1,
      monitoring: 2,
      metrics: 7,
      queues: 3,
      batches: 4,
      pending: 5,
      completed: 36,
      silenced: 3,
      failed: 6,
    };
    rerender(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );
    expect(await screen.findByText("50")).toBeVisible();

    inertia.props.navigationCounts = undefined;
    rerender(
      <TooltipProvider>
        <HorizonLayout>
          <p>Completed jobs content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );
    expect(screen.getByText("50")).toBeVisible();

    inertia.props.navigationCounts = {
      instances: 1,
      monitoring: 2,
      metrics: null,
      queues: 3,
      batches: 4,
      pending: 5,
      completed: 37,
      silenced: 3,
      failed: 6,
    };
    rerender(
      <TooltipProvider>
        <HorizonLayout>
          <p>Completed jobs content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );
    expect(await screen.findByText("51")).toBeVisible();
    expect(screen.getByRole("link", { name: "Metrics" }).parentElement).not.toHaveTextContent("7");
  });

  it("restores and persists Horizon's auto-load preference", () => {
    localStorage.setItem("horizonAutoLoadsNewEntries", "1");

    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    const autoLoad = screen.getByRole("button", { name: "Auto load new entries" });

    expect(autoLoad).toHaveAttribute("aria-pressed", "true");

    fireEvent.click(autoLoad);

    expect(localStorage.getItem("horizonAutoLoadsNewEntries")).toBe("0");
  });

  it("uses the exact handoff branding and navigation icon geometry", () => {
    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    const brand = screen.getByRole("link", { name: "Laravel Horizon" });
    const brandPath = brand.querySelector("svg path");
    const dashboard = screen.getByRole("link", { name: "Dashboard" });
    const monitoring = screen.getByRole("link", { name: "Monitoring" });
    const metrics = screen.getByRole("link", { name: "Metrics" });
    const content = screen.getByText("Dashboard content").parentElement;

    expect(brandPath).toHaveAttribute(
      "d",
      "M5.26176342 26.4094389C2.04147988 23.6582233 0 19.5675182 0 15c0-4.1421356 1.67893219-7.89213562 4.39339828-10.60660172C7.10786438 1.67893219 10.8578644 0 15 0c8.2842712 0 15 6.71572875 15 15 0 8.2842712-6.7157288 15-15 15-3.716753 0-7.11777662-1.3517984-9.73823658-3.5905611zM4.03811305 15.9222506C5.70084247 14.4569342 6.87195416 12.5 10 12.5c5 0 5 5 10 5 3.1280454 0 4.2991572-1.9569336 5.961887-3.4222502C25.4934253 8.43417206 20.7645408 4 15 4 8.92486775 4 4 8.92486775 4 15c0 .3105915.01287248.6181765.03811305.9222506z",
    );
    expect(dashboard.querySelectorAll("svg rect")).toHaveLength(4);
    expect(monitoring.querySelector("svg path")).toHaveAttribute("d", "m21 21-4.3-4.3");
    expect(metrics.querySelector("svg")).toHaveClass("lucide-chart-no-axes-combined");
    expect(brand.querySelector("svg")).toHaveClass("size-5");
    expect(content).toHaveClass(
      "p-[7px]",
      "min-[1140px]:pt-3.5",
      "min-[1140px]:pr-3.5",
      "min-[1140px]:pb-3.5",
      "min-[1140px]:pl-6",
    );
    expect(brand.closest('[data-slot="sidebar-container"]')).toHaveClass(
      "!absolute",
      "!left-0",
      "!h-full",
    );
    expect(brand.closest('[data-slot="sidebar-header"]')).not.toHaveClass("border-b");
    expect(screen.queryByText("New Dawn")).not.toBeInTheDocument();
  });

  it("does not animate the auto-load icon", () => {
    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    const autoLoad = screen.getByRole("button", { name: "Auto load new entries" });

    fireEvent.click(autoLoad);

    const autoLoadIcon = autoLoad.querySelector("svg");

    expect(autoLoadIcon).not.toHaveClass("animate-spin");
  });

  it("centers the complete sidebar and page shell inside a 1400px boundary", () => {
    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    expect(
      screen.getByText("Dashboard content").closest('[data-slot="sidebar-wrapper"]'),
    ).toHaveClass("mx-auto", "w-full", "max-w-[1400px]");
  });

  it("uses the mobile sidebar before the 1140px desktop shell can fit", async () => {
    setViewportWidth(907);

    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    const content = screen.getByText("Dashboard content");
    const trigger = await screen.findByRole("button", { name: "Toggle Sidebar" });

    expect(content.closest('[data-slot="sidebar-wrapper"]')).toHaveClass(
      "min-[1140px]:min-w-[1140px]",
    );
    expect(trigger).toHaveClass("min-[1140px]:hidden");

    fireEvent.click(trigger);

    expect(screen.getByRole("dialog", { name: "Sidebar" })).toBeVisible();
  });

  it("closes the mobile sidebar after choosing a navigation link", async () => {
    setViewportWidth(368);

    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    const trigger = await screen.findByRole("button", { name: "Toggle Sidebar" });

    fireEvent.click(trigger);

    const sidebar = screen.getByRole("dialog", { name: "Sidebar" });

    expect(sidebar).toBeVisible();

    fireEvent.click(screen.getByRole("link", { name: "Monitoring" }));

    await waitFor(() => {
      expect(screen.queryByRole("dialog", { name: "Sidebar" })).not.toBeInTheDocument();
    });
  });

  it("uses the semantic sidebar border on the mobile sheet", async () => {
    setViewportWidth(368);

    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    fireEvent.click(await screen.findByRole("button", { name: "Toggle Sidebar" }));

    expect(screen.getByRole("dialog", { name: "Sidebar" })).toHaveClass("border-sidebar-border");
  });

  it("warns when the host application is in maintenance mode", () => {
    inertia.props.horizon.maintenanceMode = true;

    render(
      <TooltipProvider>
        <HorizonLayout>
          <p>Dashboard content</p>
        </HorizonLayout>
      </TooltipProvider>,
    );

    expect(screen.getByText("Application maintenance mode")).toBeVisible();
    expect(screen.getByText(/force flag/)).toBeVisible();

    inertia.props.horizon.maintenanceMode = false;
  });
});
