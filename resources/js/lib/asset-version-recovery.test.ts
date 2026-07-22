import { describe, expect, it, vi } from "vitest";

import { recoverFromAssetVersionChange } from "@/lib/asset-version-recovery";

function locationEvent(versionChange: boolean) {
  return new CustomEvent("inertia:location", {
    cancelable: true,
    detail: {
      url: new URL("https://horizon.test/horizon/jobs/pending"),
      versionChange,
    },
  });
}

describe("recoverFromAssetVersionChange", () => {
  it("ignores location responses that do not represent a version change", () => {
    const reload = vi.fn();

    expect(recoverFromAssetVersionChange(locationEvent(false), reload)).toBeUndefined();
    expect(reload).not.toHaveBeenCalled();
  });

  it("reloads the current page and cancels Inertia's location handling after a version change", () => {
    const reload = vi.fn();

    expect(recoverFromAssetVersionChange(locationEvent(true), reload)).toBe(false);
    expect(reload).toHaveBeenCalledOnce();
  });
});
