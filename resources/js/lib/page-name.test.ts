import { describe, expect, it } from "vitest";

import { pageModulePath } from "@/lib/page-name";

describe("pageModulePath", () => {
  it("maps Inertia PascalCase page segments to kebab-case files", () => {
    expect(pageModulePath("Jobs/Show")).toBe("./pages/jobs/show.tsx");
    expect(pageModulePath("FailedJobs/Index")).toBe("./pages/failed-jobs/index.tsx");
  });
});
