import { act, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { PageHeader } from "@/components/shell/page-header";
import { TooltipProvider } from "@/components/ui/tooltip";
import { SidebarProvider } from "@/components/ui/sidebar";

type InertiaEvent = "finish" | "start";
type InertiaEventListener = (event: {
  detail: { visit: { id: string; prefetch: boolean } };
}) => void;

const inertia = vi.hoisted(() => ({
  listeners: new Map<string, Set<(event: unknown) => void>>(),
  page: {
    deferredProps: undefined as Record<string, string[]> | undefined,
    props: {} as Record<string, unknown>,
  },
}));

vi.mock("@inertiajs/react", () => ({
  router: {
    on: vi.fn((event: string, listener: (event: unknown) => void) => {
      const listeners = inertia.listeners.get(event) ?? new Set();
      listeners.add(listener);
      inertia.listeners.set(event, listeners);

      return () => listeners.delete(listener);
    }),
  },
  usePage: () => inertia.page,
}));

function emitInertiaEvent(event: InertiaEvent, visit: { id: string; prefetch: boolean }) {
  act(() => {
    inertia.listeners.get(event)?.forEach((listener) => {
      (listener as InertiaEventListener)({ detail: { visit } });
    });
  });
}

function renderHeader({
  autoLoad = false,
  children,
}: { autoLoad?: boolean; children?: ReactNode } = {}) {
  return render(
    <TooltipProvider>
      <SidebarProvider>
        <PageHeader status="running" autoLoad={autoLoad} onAutoLoadChange={vi.fn()} />
        {children}
      </SidebarProvider>
    </TooltipProvider>,
  );
}

describe("PageHeader", () => {
  beforeEach(() => {
    inertia.listeners.clear();
    inertia.page.deferredProps = undefined;
    inertia.page.props = {};
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockReturnValue({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      }),
    });
  });

  it("rotates the refresh icon until every active Inertia request finishes", () => {
    renderHeader({ autoLoad: true });

    const refresh = screen.getByRole("button", { name: "Auto load new entries" });
    const icon = refresh.querySelector("svg");

    expect(icon).not.toHaveClass("animate-spin");

    emitInertiaEvent("start", { id: "deferred", prefetch: false });
    emitInertiaEvent("start", { id: "poll", prefetch: false });

    expect(icon).toHaveClass("animate-spin");
    expect(refresh).toHaveAttribute("aria-busy", "true");

    emitInertiaEvent("finish", { id: "deferred", prefetch: false });

    expect(icon).toHaveClass("animate-spin");

    emitInertiaEvent("finish", { id: "poll", prefetch: false });

    expect(icon).not.toHaveClass("animate-spin");
    expect(refresh).toHaveAttribute("aria-busy", "false");
  });

  it("does not rotate the refresh icon for background prefetches", () => {
    renderHeader({ autoLoad: true });

    const refresh = screen.getByRole("button", { name: "Auto load new entries" });

    emitInertiaEvent("start", { id: "prefetch", prefetch: true });

    expect(refresh.querySelector("svg")).not.toHaveClass("animate-spin");
    expect(refresh).toHaveAttribute("aria-busy", "false");
  });

  it("rotates while the current page still has unresolved deferred props", () => {
    inertia.page.deferredProps = {
      dashboard: ["summary", "workload"],
    };

    renderHeader({ autoLoad: true });

    const refresh = screen.getByRole("button", { name: "Auto load new entries" });

    expect(refresh.querySelector("svg")).toHaveClass("animate-spin");
    expect(refresh).toHaveAttribute("aria-busy", "true");
  });

  it("does not indicate background refresh requests while automatic refresh is disabled", () => {
    renderHeader();

    const refresh = screen.getByRole("button", { name: "Auto load new entries" });
    const icon = refresh.querySelector("svg");

    emitInertiaEvent("start", { id: "poll", prefetch: false });

    expect(icon).not.toHaveClass("animate-spin");
    expect(icon).toHaveStyle({ rotate: "0turn" });
    expect(refresh).toHaveAttribute("aria-busy", "false");
  });
});
