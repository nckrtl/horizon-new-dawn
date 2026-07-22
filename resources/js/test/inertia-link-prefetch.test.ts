import { describe, expect, it } from "vitest";

const sources = import.meta.glob<string>("/resources/js/**/*.tsx", {
  eager: true,
  import: "default",
  query: "?raw",
});

describe("Inertia links", () => {
  it("prefetches every internal destination on hover", () => {
    const missing: string[] = [];

    Object.entries(sources)
      .filter(([path]) => !path.includes(".test.") && !path.includes("/test/"))
      .forEach(([path, source]) => {
        for (const match of source.matchAll(/<Link\b[\s\S]*?>/g)) {
          if (!/\bprefetch(?:\s|=|\/|>)/.test(match[0])) {
            const line = source.slice(0, match.index).split("\n").length;

            missing.push(`${path}:${line}`);
          }
        }
      });

    expect(missing).toEqual([]);
  });
});
