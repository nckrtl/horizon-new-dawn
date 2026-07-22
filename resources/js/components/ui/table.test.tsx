import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

describe("Table", () => {
  it("uses one separator where a card header meets a table", () => {
    render(
      <Card>
        <CardHeader>
          <CardTitle>Jobs</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Job</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow>
                <TableCell>Import catalog</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>,
    );

    expect(screen.getByText("Jobs").closest("[data-slot=card-header]")).toHaveClass("border-b");

    const headerRow = screen.getByRole("row", { name: "Job" });

    expect(headerRow).not.toHaveClass("border-t");
    expect(headerRow).toHaveClass("border-b", "border-separator");
    expect(screen.getByText("Import catalog").closest("tbody")).toHaveClass(
      "[&_tr:last-child]:border-0",
    );
  });
});
