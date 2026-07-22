import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { MetricsTable } from "@/components/metrics/metrics-table";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

describe("MetricsTable", () => {
  it("matches the handoff's single sortable metric-name column and encodes detail routes", () => {
    render(
      <MetricsTable
        type="jobs"
        horizonBaseUrl="/horizon"
        metrics={[
          { name: "App\\Jobs\\SlowExport", throughput: 4, runtime: 7.25 },
          { name: "App\\Jobs\\FastImport", throughput: 18, runtime: 0.125 },
        ]}
      />,
    );

    expect(screen.getAllByRole("columnheader")).toHaveLength(1);
    expect(screen.getByRole("link", { name: "App\\Jobs\\FastImport" })).toHaveAttribute(
      "href",
      "/horizon/metrics/jobs/App%5CJobs%5CFastImport",
    );

    fireEvent.click(screen.getByRole("button", { name: "Sort by Job ascending" }));
    const rows = within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row");

    expect(rows[0]).toHaveTextContent("FastImport");
    expect(rows[1]).toHaveTextContent("SlowExport");
  });

  it("explains when Horizon has not recorded job metrics yet", () => {
    render(<MetricsTable type="jobs" horizonBaseUrl="/horizon" metrics={[]} />);

    expect(screen.getByText("No job metrics yet")).toBeVisible();
    expect(
      screen.getByText(
        "Once Horizon records its first job snapshot, throughput and runtime history will settle in here.",
      ),
    ).toBeVisible();
    expect(document.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(
      "lucide-chart-no-axes-combined",
    );
  });
});
