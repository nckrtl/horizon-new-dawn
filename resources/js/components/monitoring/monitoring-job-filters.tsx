import { emptyJobFilterValues, JobFilters, jobFilterKeys } from "@/components/jobs/job-filters";
import type { JobRow } from "@/types/jobs";

export function MonitoringJobFilters({
  jobs,
  jobFilter,
  queueFilter,
  onJobFilterChange,
  onQueueFilterChange,
  onClearFilters,
}: {
  jobs: readonly JobRow[];
  jobFilter: string | null;
  queueFilter: string | null;
  onJobFilterChange: (value: string | null) => void;
  onQueueFilterChange: (value: string | null) => void;
  onClearFilters: () => void;
}) {
  return (
    <JobFilters
      jobs={jobs}
      filterKeys={jobFilterKeys("monitoring")}
      values={{ ...emptyJobFilterValues, job: jobFilter, queue: queueFilter }}
      onClearFilters={onClearFilters}
      onFilterChange={(filterKey, value) => {
        if (filterKey === "job") {
          onJobFilterChange(value);
        }

        if (filterKey === "queue") {
          onQueueFilterChange(value);
        }
      }}
      description="Narrow the loaded jobs by job class or queue."
    />
  );
}
