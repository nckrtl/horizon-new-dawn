import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { formatMetricTooltipLabel, MetricChart } from "@/components/metrics/metric-chart";

const snapshots = [
  { timestamp: 1_784_387_100, throughput: 12, runtime: 1.5 },
  { timestamp: 1_784_387_400, throughput: 18, runtime: 2.121 },
];

describe("MetricChart", () => {
  it("formats the snapshot timestamp instead of the series label", () => {
    const timestamp = 1_784_387_100;

    expect(formatMetricTooltipLabel("Jobs per minute", [{ payload: { timestamp } }])).toBe(
      new Intl.DateTimeFormat(undefined, {
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(timestamp * 1000)),
    );
  });

  it("renders an accessible shadcn throughput chart", () => {
    render(<MetricChart kind="throughput" snapshots={snapshots} />);

    expect(screen.getByRole("img", { name: "Throughput metric chart" })).toBeVisible();
    expect(screen.getByRole("img", { name: "Throughput metric chart" })).toHaveAttribute(
      "data-animation",
      "disabled",
    );
    expect(screen.getByText("Latest throughput: 18 jobs per minute.")).toHaveClass("sr-only");
  });

  it("formats runtime chart values in seconds", () => {
    render(<MetricChart kind="runtime" snapshots={snapshots} />);

    expect(screen.getByRole("img", { name: "Runtime metric chart" })).toBeVisible();
    expect(screen.getByText("Latest runtime: 2.121 seconds.")).toHaveClass("sr-only");
  });

  it("renders missing runtime observations as gaps without announcing a fabricated zero", () => {
    const bounds = vi
      .spyOn(HTMLElement.prototype, "getBoundingClientRect")
      .mockReturnValue(new DOMRect(0, 0, 960, 256));
    const runtimeSnapshots = [
      ...snapshots,
      { timestamp: 1_784_387_700, throughput: 0, runtime: null },
    ];
    const { container } = render(<MetricChart kind="runtime" snapshots={runtimeSnapshots} />);

    expect(screen.getByText("Latest runtime: no observation.")).toHaveClass("sr-only");
    expect(container.querySelectorAll(".recharts-bar-rectangle")).toHaveLength(2);

    bounds.mockRestore();
  });

  it("disables chart animation for reduced-motion preferences", () => {
    Object.defineProperty(window, "matchMedia", {
      configurable: true,
      value: vi.fn().mockReturnValue({
        matches: true,
        media: "(prefers-reduced-motion: reduce)",
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
      } as MediaQueryList),
    });

    render(<MetricChart kind="throughput" snapshots={snapshots} />);

    expect(screen.getByRole("img", { name: "Throughput metric chart" })).toHaveAttribute(
      "data-animation",
      "disabled",
    );
  });

  it("uses the shadcn empty state when there are not enough snapshots", () => {
    render(<MetricChart kind="runtime" snapshots={[]} />);

    expect(screen.getByText("Not Enough Data")).toBeVisible();
    expect(
      screen.getByText("Horizon needs at least one metrics snapshot to draw this chart."),
    ).toBeVisible();
    expect(document.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(
      "lucide-chart-no-axes-combined",
    );
  });
});
