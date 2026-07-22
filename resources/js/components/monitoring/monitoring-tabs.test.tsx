import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { MonitoringTabs } from "@/components/monitoring/monitoring-tabs";

vi.mock("@inertiajs/react", () => ({
  Link: ({
    children,
    href,
    prefetch: _prefetch,
    preserveState,
    ...props
  }: {
    children: ReactNode;
    href: string;
    prefetch?: boolean;
    preserveState?: boolean;
  }) => (
    <a href={href} data-preserve-state={preserveState} {...props}>
      {children}
    </a>
  ),
}));

describe("MonitoringTabs", () => {
  beforeEach(() => {
    window.history.replaceState({}, "", "/horizon/monitoring/customer%3A42/failed");
  });

  it("links both statuses through dedicated routes while preserving page state", () => {
    render(
      <MonitoringTabs
        tag={"App\\Models\\Order:1043"}
        status="failed"
        horizonBaseUrl="/horizon"
        trackedCount={4}
        failedCount={2}
        jobFilter={null}
        queueFilter={null}
      />,
    );

    expect(screen.getByRole("tab", { name: /Recent Jobs/ })).toHaveAttribute(
      "href",
      "/horizon/monitoring/App%5CModels%5COrder%3A1043/jobs",
    );
    expect(screen.getByRole("tab", { name: /Failed Jobs/ })).toHaveAttribute(
      "href",
      "/horizon/monitoring/App%5CModels%5COrder%3A1043/failed",
    );
    expect(screen.getByRole("tab", { name: /Recent Jobs/ })).toHaveTextContent("4");
    expect(screen.getByRole("tab", { name: /Failed Jobs/ })).toHaveTextContent("2");
    for (const tab of screen.getAllByRole("tab")) {
      expect(tab).toHaveAttribute("data-preserve-state", "true");
    }
    expect(screen.getByRole("tab", { name: /Failed Jobs/ })).toHaveAttribute(
      "aria-selected",
      "true",
    );
    expect(screen.getByRole("tablist", { name: "Monitored tag job status" })).not.toHaveClass(
      "border-b",
    );
    expect(screen.getByRole("tablist", { name: "Monitored tag job status" })).toHaveClass(
      "before:h-px",
      "before:bg-separator",
    );
  });

  it("preserves job filters across status tabs while resetting the cursor", () => {
    window.history.replaceState(
      {},
      "",
      "/horizon/monitoring/customer%3A42/jobs?starting_at=50&tab=jobs",
    );

    render(
      <MonitoringTabs
        tag="customer:42"
        status="jobs"
        horizonBaseUrl="/horizon"
        trackedCount={4}
        failedCount={2}
        jobFilter={"App\\Jobs\\DelayedExport"}
        queueFilter="exports"
      />,
    );

    expect(screen.getByRole("tab", { name: /Recent Jobs/ })).toHaveAttribute(
      "href",
      "/horizon/monitoring/customer%3A42/jobs?job=App%5CJobs%5CDelayedExport&queue=exports",
    );
    expect(screen.getByRole("tab", { name: /Failed Jobs/ })).toHaveAttribute(
      "href",
      "/horizon/monitoring/customer%3A42/failed?job=App%5CJobs%5CDelayedExport&queue=exports",
    );
  });
});
