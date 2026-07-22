import type { Page } from "@inertiajs/core";
import { App } from "@inertiajs/react";
import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { TooltipProvider } from "@/components/ui/tooltip";
import { HorizonLayout } from "@/layouts/horizon-layout";
import type { HorizonPageProps } from "@/types/page";

function DashboardPage() {
  return <p>Dashboard content</p>;
}

const initialPage: Page<HorizonPageProps> = {
  component: "dashboard",
  props: {
    errors: {},
    horizon: {
      baseUrl: "/horizon",
      pollInterval: 5000,
      status: "running",
      processing: false,
      maintenanceMode: false,
    },
    meta: { title: "Dashboard", activeNavigation: "dashboard" },
  },
  url: "/horizon",
  version: null,
  rescuedProps: [],
  flash: {},
  rememberedState: {},
};

describe("HorizonLayout", () => {
  beforeEach(() => {
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

  it("renders page content through Inertia's default persistent layout", () => {
    render(
      <TooltipProvider>
        <App
          initialComponent={DashboardPage}
          initialPage={initialPage}
          resolveComponent={() => DashboardPage}
          defaultLayout={() => HorizonLayout}
        />
      </TooltipProvider>,
    );

    expect(screen.getByText("Dashboard content")).toBeVisible();
    expect(screen.getByRole("link", { name: "Laravel Horizon" })).toBeVisible();
    expect(screen.queryByRole("heading", { name: "Dashboard" })).not.toBeInTheDocument();
  });
});
