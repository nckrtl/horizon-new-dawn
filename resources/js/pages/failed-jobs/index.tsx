import { Head, InfiniteScroll, router } from "@inertiajs/react";
import { useEffect, useState } from "react";

import { FailedJobsActionsMenu } from "@/components/jobs/failed-job-actions";
import { FailedJobTable } from "@/components/jobs/failed-job-table";
import { JobFilters } from "@/components/jobs/job-filters";
import { JobsPage } from "@/components/jobs/jobs-page";
import { Skeleton } from "@/components/ui/skeleton";
import { index as failedJobsIndex } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { useAutoLoad } from "@/hooks/use-auto-load";
import { useJobFilters } from "@/hooks/use-job-filters";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { urlWithCurrentQuery } from "@/lib/url-query";
import type { FailedJobsPageProps } from "@/types/jobs";

function FailedJobsIndex({ horizon, query, jobs }: FailedJobsPageProps) {
  const [search, setSearch] = useState(query);
  const { autoLoad } = useAutoLoadPreference();
  const refreshedJobs = useAutoLoad({
    enabled: autoLoad,
    prop: "jobs",
    interval: horizon.pollInterval,
    polling: search.trim() === query,
    items: jobs.data,
    scope: query,
  });
  const jobFilters = useJobFilters("failed", refreshedJobs.items);
  const hasSearch = query !== "";
  const hasFilters = jobFilters.activeFilterCount > 0;
  let emptyDescription: string | undefined;

  if (hasSearch && hasFilters) {
    emptyDescription = "No loaded failed jobs match this exact tag and the current filters.";
  } else if (hasSearch) {
    emptyDescription = `No failed jobs match the exact tag “${query}”.`;
  } else if (hasFilters) {
    emptyDescription = "No loaded failed jobs match the current filters.";
  }

  useEffect(() => {
    const value = search.trim();

    if (value === query) {
      return;
    }

    const timer = window.setTimeout(() => {
      const route = resolveHorizonRoute(failedJobsIndex(), horizon.baseUrl);
      const url = urlWithCurrentQuery(route.url, { tag: value }, ["starting_at"]);

      router.get(
        url,
        {},
        {
          only: ["query", "jobs", "horizon"],
          preserveScroll: true,
          preserveState: true,
          replace: true,
          reset: ["jobs"],
        },
      );
    }, 500);

    return () => window.clearTimeout(timer);
  }, [horizon.baseUrl, query, search]);

  return (
    <>
      <Head title="Jobs" />
      <JobsPage
        activeTab="failed"
        horizonBaseUrl={horizon.baseUrl}
        search={search}
        searchLabel="Filter failed jobs by exact tag"
        searchPlaceholder="Enter an exact tag"
        onSearchChange={setSearch}
        filters={
          <JobFilters
            jobs={refreshedJobs.items}
            filterKeys={jobFilters.availableFilterKeys}
            values={jobFilters.values}
            onFilterChange={jobFilters.setFilterValue}
            description="Narrow the loaded failed jobs using filters available for this tab."
          />
        }
        actions={
          <FailedJobsActionsMenu
            horizonBaseUrl={horizon.baseUrl}
            hasFailedJobs={jobs.total > 0}
            retryable={jobs.retryable}
          />
        }
      >
        <InfiniteScroll data="jobs" onlyNext buffer={600} loading={<LoadingRows />}>
          <FailedJobTable
            jobs={jobFilters.filteredJobs}
            horizonBaseUrl={horizon.baseUrl}
            available={jobs.available}
            message={jobs.message}
            hasNewEntries={refreshedJobs.hasNewEntries}
            onLoadNewEntries={refreshedJobs.loadNewEntries}
            emptyTitle={hasSearch || hasFilters ? "No matching failed jobs" : undefined}
            emptyDescription={emptyDescription}
          />
        </InfiniteScroll>
      </JobsPage>
    </>
  );
}

function LoadingRows() {
  return (
    <div className="flex flex-col gap-2 border-t p-4" aria-label="Loading more failed jobs">
      <Skeleton className="h-12 w-full" />
      <Skeleton className="h-12 w-full" />
    </div>
  );
}

export default FailedJobsIndex;
