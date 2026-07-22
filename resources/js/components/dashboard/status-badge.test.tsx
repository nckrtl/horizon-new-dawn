import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { StatusBadge } from "@/components/dashboard/status-badge";
import type { HorizonStatus } from "@/types/dashboard";

describe("StatusBadge", () => {
  it.each([
    ["running", "Running"],
    ["paused", "Paused"],
    ["inactive", "Inactive"],
    ["unavailable", "Unavailable"],
  ] satisfies [HorizonStatus, string][])("labels the %s state", (status, label) => {
    render(<StatusBadge status={status} />);

    expect(screen.getByText(label)).toHaveAttribute("data-status", status);
  });
});
