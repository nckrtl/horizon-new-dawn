import { useEffect, useState } from "react";

const maximumTimeout = 2_147_483_647;

export function useScheduledJobClock(jobs: readonly { scheduledAt: number | null }[]): number {
  const [revision, setRevision] = useState(0);
  const now = Date.now() / 1000;
  let nextScheduledAt: number | null = null;

  for (const job of jobs) {
    if (
      job.scheduledAt !== null &&
      job.scheduledAt > now &&
      (nextScheduledAt === null || job.scheduledAt < nextScheduledAt)
    ) {
      nextScheduledAt = job.scheduledAt;
    }
  }

  useEffect(() => {
    if (nextScheduledAt === null) {
      return;
    }

    const milliseconds = Math.min(
      maximumTimeout,
      Math.max(0, (nextScheduledAt - Date.now() / 1000) * 1000 + 1),
    );
    const timeout = window.setTimeout(() => {
      setRevision((current) => current + 1);
    }, milliseconds);

    return () => window.clearTimeout(timeout);
  }, [nextScheduledAt, revision]);

  return now;
}
