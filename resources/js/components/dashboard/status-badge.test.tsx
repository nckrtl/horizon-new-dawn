import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { StatusBadge } from "@/components/dashboard/status-badge";
import type { HorizonDisplayStatus } from "@/types/dashboard";

describe("StatusBadge", () => {
  it.each([
    ["running", "Running"],
    ["continuing", "Continuing"],
    ["pausing", "Pausing"],
    ["paused", "Paused"],
    ["inactive", "Inactive"],
    ["unavailable", "Unavailable"],
  ] satisfies [HorizonDisplayStatus, string][])("labels the %s state", (status, label) => {
    render(<StatusBadge status={status} />);

    expect(screen.getByText(label)).toHaveAttribute("data-status", status);
  });
});
