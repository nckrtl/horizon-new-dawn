import { render } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import QueueShow from "@/pages/queues/show";
import type { QueueShowPageProps } from "@/types/queues";

const usePageRefresh = vi.hoisted(() => vi.fn());

vi.mock("@inertiajs/react", () => ({ Head: () => null }));
vi.mock("@/components/queues/queue-activity-tabs", () => ({ QueueActivityTabs: () => null }));
vi.mock("@/components/queues/queue-overview", () => ({ QueueOverview: () => null }));
vi.mock("@/hooks/use-dashboard-refresh", () => ({ usePageRefresh }));
vi.mock("@/layouts/horizon-layout", () => ({
  useAutoLoadPreference: () => ({ autoLoad: true }),
}));

const props = {
  horizon: { baseUrl: "/horizon", pollInterval: 5_000 },
  queue: "default",
  view: "overview",
  tab: "pending",
  summary: {},
  preview: {},
  activity: { data: [] },
} as unknown as QueueShowPageProps;

describe("queue detail refresh ownership", () => {
  beforeEach(() => {
    usePageRefresh.mockReset();
  });

  it("keeps query-derived tab and view props out of background refreshes", () => {
    const { rerender } = render(<QueueShow {...props} />);

    expect(usePageRefresh).toHaveBeenLastCalledWith(5_000, ["summary", "activity"], true, [
      "activity",
    ]);

    rerender(<QueueShow {...props} view="metrics" />);

    expect(usePageRefresh).toHaveBeenLastCalledWith(
      5_000,
      ["summary", "activity", "preview"],
      true,
      ["activity"],
    );
  });
});
