import { render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";

import { MetricsTabs } from "@/components/metrics/metrics-tabs";

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

describe("MetricsTabs", () => {
  it("links jobs and queues through dedicated routes while preserving layout state", () => {
    render(<MetricsTabs type="queues" horizonBaseUrl="/horizon" />);

    expect(screen.getByRole("tab", { name: "Jobs" })).toHaveAttribute(
      "href",
      "/horizon/metrics/jobs",
    );
    expect(screen.getByRole("tab", { name: "Queues" })).toHaveAttribute(
      "href",
      "/horizon/metrics/queues",
    );
    for (const tab of screen.getAllByRole("tab")) {
      expect(tab).toHaveAttribute("data-preserve-state", "true");
    }
    expect(screen.getByRole("tab", { name: "Queues" })).toHaveAttribute("aria-selected", "true");
  });

  it("uses one table separator and a purple active-tab indicator", () => {
    render(<MetricsTabs type="jobs" horizonBaseUrl="/horizon" />);

    expect(screen.getByRole("tablist", { name: "Metrics type" })).not.toHaveClass("border-b");
    expect(screen.getByRole("tablist", { name: "Metrics type" })).toHaveClass(
      "before:h-px",
      "before:bg-separator",
    );
    expect(screen.getByRole("tab", { name: "Jobs" })).toHaveClass("after:bg-primary");
  });
});
