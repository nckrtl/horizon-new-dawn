// @vitest-environment node

import { describe, expect, it } from "vitest";

import viteConfig from "../../vite.config";

describe("Vite package build", () => {
  it("uses a relative base so published assets work below any host path", () => {
    expect(viteConfig).toHaveProperty("base", "./");
  });
});
