import { Head, InfiniteScroll } from "@inertiajs/react";
import { useDeferredValue, useMemo, useState } from "react";

import { JobFilters } from "@/components/jobs/job-filters";
import { JobTable } from "@/components/jobs/job-table";
import { JobsPage } from "@/components/jobs/jobs-page";
import { PendingJobsActions } from "@/components/jobs/pending-jobs-actions";
import { Skeleton } from "@/components/ui/skeleton";
import { useAutoLoad } from "@/hooks/use-auto-load";
import { useJobFilters } from "@/hooks/use-job-filters";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import type { JobsPageProps } from "@/types/jobs";

function JobsIndex({ horizon, type, jobs }: JobsPageProps) {
  return (
    <>
      <Head title="Jobs" />
      <JobsContent key={type} horizon={horizon} type={type} jobs={jobs} />
    </>
  );
}

function JobsContent({ horizon, type, jobs }: JobsPageProps) {
  const [search, setSearch] = useState("");
  const deferredSearch = useDeferredValue(search);
  const { autoLoad } = useAutoLoadPreference();
  const refreshedJobs = useAutoLoad({
    enabled: autoLoad,
    prop: "jobs",
    interval: horizon.pollInterval,
    items: jobs.data,
    scope: type,
  });
  const jobFilters = useJobFilters(type, refreshedJobs.items);
  const visibleJobs = useMemo(() => {
    const query = deferredSearch.trim().toLocaleLowerCase();

    if (query === "") {
      return jobFilters.filteredJobs;
    }

    return jobFilters.filteredJobs.filter((job) =>
      [job.id, job.name, job.shortName, job.connection, job.queue, ...job.tags]
        .join(" ")
        .toLocaleLowerCase()
        .includes(query),
    );
  }, [deferredSearch, jobFilters.filteredJobs]);
  const hasSearch = search.trim().length > 0;
  const hasFilters = jobFilters.activeFilterCount > 0;
  let emptyDescription: string | undefined;

  if (hasSearch && hasFilters) {
    emptyDescription = "No loaded jobs match your search and filters.";
  } else if (hasSearch) {
    emptyDescription = "No loaded jobs match your search.";
  } else if (hasFilters) {
    emptyDescription = "No loaded jobs match the current filters.";
  }

  return (
    <JobsPage
      activeTab={type}
      horizonBaseUrl={horizon.baseUrl}
      search={search}
      searchLabel={`Search ${type} jobs`}
      searchPlaceholder={`Search ${type} jobs`}
      onSearchChange={setSearch}
      filters={
        <JobFilters
          jobs={refreshedJobs.items}
          filterKeys={jobFilters.availableFilterKeys}
          values={jobFilters.values}
          onFilterChange={jobFilters.setFilterValue}
          description={`Narrow the loaded ${type} jobs using filters available for this tab.`}
        />
      }
      actions={
        type === "pending" ? (
          <PendingJobsActions horizonBaseUrl={horizon.baseUrl} disabled={!jobs.available} />
        ) : null
      }
    >
      <InfiniteScroll data="jobs" onlyNext buffer={600} loading={<LoadingRows />}>
        <JobTable
          jobs={visibleJobs}
          type={type}
          horizonBaseUrl={horizon.baseUrl}
          available={jobs.available}
          message={jobs.message}
          hasNewEntries={refreshedJobs.hasNewEntries}
          onLoadNewEntries={refreshedJobs.loadNewEntries}
          emptyTitle={hasSearch || hasFilters ? `No matching ${type} jobs` : undefined}
          emptyDescription={emptyDescription}
        />
      </InfiniteScroll>
    </JobsPage>
  );
}

function LoadingRows() {
  return (
    <div className="flex flex-col gap-2 border-t p-4" aria-label="Loading more jobs">
      <Skeleton className="h-9 w-full" />
      <Skeleton className="h-9 w-full" />
    </div>
  );
}

export default JobsIndex;
