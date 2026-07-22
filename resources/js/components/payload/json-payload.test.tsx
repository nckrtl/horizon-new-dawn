import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { JsonPayload } from "@/components/payload/json-payload";

describe("JsonPayload", () => {
  it("renders normalized JSON as escaped text", () => {
    const { container } = render(
      <JsonPayload value={{ command: "<script>alert('unsafe')</script>", attempts: 2 }} />,
    );

    expect(screen.getByText(/<script>alert\('unsafe'\)<\/script>/)).toBeVisible();
    expect(container.querySelector("script")).not.toBeInTheDocument();
    expect(container.innerHTML).toContain("&lt;script&gt;");
  });

  it("provides a readable unavailable state", () => {
    render(<JsonPayload value={null} />);

    expect(screen.getByText("Payload unavailable")).toBeVisible();
  });
});
