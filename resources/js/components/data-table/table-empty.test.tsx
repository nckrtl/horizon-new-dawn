import { render, screen, within } from "@testing-library/react";
import { ChartNoAxesCombinedIcon } from "lucide-react";
import { describe, expect, it } from "vitest";

import { TableEmpty } from "@/components/data-table/table-empty";
import { Table, TableBody } from "@/components/ui/table";

describe("TableEmpty", () => {
  it("renders its message as a visible shadcn empty state", () => {
    render(
      <Table>
        <TableBody>
          <TableEmpty
            columns={2}
            title="No results yet"
            description="Horizon will place new results here."
          />
        </TableBody>
      </Table>,
    );

    const row = screen.getByRole("row", { name: "No results yet" });

    expect(row.querySelector('[data-slot="empty"]')).toBeVisible();
    expect(within(row).getByText("No results yet")).toBeVisible();
    expect(within(row).getByText("Horizon will place new results here.")).toBeVisible();
  });

  it("renders a supplied empty-state icon", () => {
    render(
      <Table>
        <TableBody>
          <TableEmpty
            columns={1}
            title="No metrics yet"
            description="Horizon will place metrics here."
            icon={ChartNoAxesCombinedIcon}
          />
        </TableBody>
      </Table>,
    );

    const row = screen.getByRole("row", { name: "No metrics yet" });

    expect(row.querySelector('[data-slot="empty-icon"] svg')).toBeInTheDocument();
  });
});
