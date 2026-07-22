import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { RecentFailuresPanel } from "@/components/dashboard/recent-failures";

describe("RecentFailuresPanel", () => {
  it("renders only the safe failure preview fields", () => {
    render(
      <RecentFailuresPanel
        failures={{
          available: true,
          message: null,
          items: [
            {
              id: "failure-1",
              name: "App\\Jobs\\SendInvoice",
              queue: "billing",
              failedAt: 1_752_770_400,
            },
          ],
        }}
      />,
    );

    expect(screen.getByText("App\\Jobs\\SendInvoice")).toBeVisible();
    expect(screen.getByText("billing")).toBeVisible();
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
  });

  it("renders a calm empty state", () => {
    render(<RecentFailuresPanel failures={{ available: true, message: null, items: [] }} />);

    expect(screen.getByText("No recent failures")).toBeVisible();
    expect(document.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(
      "lucide-layout-dashboard",
    );
  });
});
