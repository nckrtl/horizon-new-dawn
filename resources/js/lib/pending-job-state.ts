export type PendingJobState = "ready" | "reserved" | "delayed" | "released";

export function pendingJobState(
  job: { status: string; scheduledAt: number | null },
  now: number,
): PendingJobState {
  if (job.status === "reserved") {
    return "reserved";
  }

  if (job.scheduledAt === null) {
    return "ready";
  }

  return job.scheduledAt > now ? "delayed" : "released";
}
