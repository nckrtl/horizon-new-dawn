import { describe, expect, it } from "vitest";

import {
  emptyJobFilterValues,
  jobFilterKeys,
  matchesJobFilters,
} from "@/components/jobs/job-filters";
import type { JobRow } from "@/types/jobs";

const scheduledJob: JobRow = {
  id: "job-scheduled",
  index: 0,
  name: "App\\Jobs\\ScheduledJob",
  shortName: "ScheduledJob",
  connection: "redis",
  queue: "default",
  status: "pending",
  tags: [],
  attempts: 0,
  retryOf: null,
  delay: 60,
  scheduledAt: 1_100,
  originalScheduledAt: 1_100,
  pushedAt: 1_000,
  reservedAt: null,
  completedAt: null,
  failedAt: null,
  runtime: null,
  occurredAt: 1_000,
  retried: false,
  retryCompleted: false,
  retryCount: 0,
  latestRetryStatus: null,
  retryEligible: false,
};

describe("pending job state filters", () => {
  it("distinguishes delayed, released, and never-scheduled ready jobs", () => {
    const delayed = {
      ...emptyJobFilterValues,
      state: "delayed",
    };
    const released = {
      ...emptyJobFilterValues,
      state: "released",
    };

    expect(matchesJobFilters(scheduledJob, jobFilterKeys("pending"), delayed, 1_099)).toBe(true);
    expect(matchesJobFilters(scheduledJob, jobFilterKeys("pending"), released, 1_100)).toBe(true);
    expect(
      matchesJobFilters(
        { ...scheduledJob, scheduledAt: null },
        jobFilterKeys("pending"),
        { ...emptyJobFilterValues, state: "ready" },
        1_100,
      ),
    ).toBe(true);
  });
});
