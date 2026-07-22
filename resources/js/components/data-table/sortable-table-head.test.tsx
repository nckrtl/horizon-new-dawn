import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { Table, TableHeader, TableRow } from "@/components/ui/table";

function renderHead(props: React.ComponentProps<typeof SortableTableHead>) {
  return render(
    <Table>
      <TableHeader>
        <TableRow>
          <SortableTableHead {...props} />
        </TableRow>
      </TableHeader>
    </Table>,
  );
}

describe("SortableTableHead", () => {
  it("exposes sort state and activates through a real button", () => {
    const onSort = vi.fn();
    renderHead({ label: "Runtime", columnKey: "runtime", direction: "asc", onSort });

    expect(screen.getByRole("columnheader", { name: /runtime/i })).toHaveAttribute(
      "aria-sort",
      "ascending",
    );
    fireEvent.click(screen.getByRole("button", { name: "Sort by Runtime descending" }));
    expect(onSort).toHaveBeenCalledWith("runtime");
  });

  it("renders action headers without fake sorting controls", () => {
    const { container } = renderHead({ label: "Actions" });

    expect(screen.getByRole("columnheader", { name: "Actions" })).not.toHaveAttribute("aria-sort");
    expect(within(container).queryByRole("button")).not.toBeInTheDocument();
  });
});
