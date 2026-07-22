import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { StackTrace } from "@/components/jobs/stack-trace";

describe("StackTrace", () => {
  it("shows the complete handoff-style trace without a custom disclosure", () => {
    const trace = [
      "RuntimeException: Intentional failure in /app/Job.php:21",
      "Stack trace:",
      ...Array.from(
        { length: 9 },
        (_, index) => `#${index} /app/Job.php(${index + 1}): App\\Job->handle()`,
      ),
    ];

    const { container } = render(<StackTrace value={trace.join("\n")} />);

    expect(container.querySelector('[data-trace-token="exception"]')).toHaveTextContent(
      "RuntimeException",
    );
    expect(container.querySelector('[data-trace-token="frame"]')).toHaveTextContent("#0");
    expect(container.querySelector('[data-trace-token="path"]')).toHaveTextContent("/app/Job.php(");
    expect(container.querySelector('[data-trace-token="line"]')).toHaveTextContent("1");
    expect(container).toHaveTextContent("#8 /app/Job.php(9): App\\Job->handle()");
    expect(screen.queryByRole("button", { name: "Show all stack frames" })).not.toBeInTheDocument();
  });
});
