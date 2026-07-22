import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { SupervisorsTable } from "@/components/dashboard/supervisors-table";

vi.mock("@inertiajs/react", async () => {
  const { inertiaTestMocks } = await import("@/test/inertia-mock");

  return inertiaTestMocks();
});

describe("SupervisorsTable", () => {
  it("renders supervisor groups and sorts their rows", () => {
    render(
      <SupervisorsTable
        horizonBaseUrl="/horizon"
        supervisors={{
          available: true,
          message: null,
          groups: [
            {
              name: "horizon-web-01",
              status: "running",
              local: true,
              items: [
                {
                  id: "horizon-web-01:supervisor-heavy",
                  name: "supervisor-heavy",
                  connection: "redis",
                  queues: ["default", "mail"],
                  processes: 10,
                  balancing: "Auto",
                  status: "paused",
                },
                {
                  id: "horizon-web-01:supervisor-light",
                  name: "supervisor-light",
                  connection: "redis",
                  queues: ["exports"],
                  processes: 2,
                  balancing: "Simple",
                  status: "running",
                },
              ],
            },
          ],
        }}
      />,
    );

    expect(screen.getByRole("heading", { name: "Instances" })).toBeVisible();
    expect(screen.getByRole("heading", { name: "horizon-web-01" })).toBeVisible();
    expect(screen.getAllByText("Running")).toHaveLength(2);
    expect(screen.getByText("Paused")).toBeVisible();
    fireEvent.click(screen.getByRole("button", { name: "Sort by Processes ascending" }));

    const rows = within(screen.getAllByRole("rowgroup")[1]).getAllByRole("row");
    expect(rows[0]).toHaveTextContent("supervisor-light");
    expect(rows[0]).toHaveTextContent("Running");
    expect(rows[1]).toHaveTextContent("supervisor-heavy");
    expect(rows[1]).toHaveTextContent("Paused");
  });

  it("uses the Dashboard navigation icon for an empty supervisor group", () => {
    render(
      <SupervisorsTable
        horizonBaseUrl="/horizon"
        supervisors={{
          available: true,
          message: null,
          groups: [{ name: "horizon-web-01", status: "running", local: false, items: [] }],
        }}
      />,
    );

    const row = screen.getByRole("row", { name: "No supervisors" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toHaveClass(
      "lucide-layout-dashboard",
    );
  });
});
