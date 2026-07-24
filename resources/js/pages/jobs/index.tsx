import { Head, InfiniteScroll, usePage } from "@inertiajs/react";
import { useDeferredValue, useEffect, useMemo, useRef, useState } from "react";

import { JobFilters } from "@/components/jobs/job-filters";
import { JobTable } from "@/components/jobs/job-table";
import { JobsPage } from "@/components/jobs/jobs-page";
import { PendingJobsActions } from "@/components/jobs/pending-jobs-actions";
import { Skeleton } from "@/components/ui/skeleton";
import { useAutoLoad } from "@/hooks/use-auto-load";
import { useJobFilters } from "@/hooks/use-job-filters";
import { useScheduledJobClock } from "@/hooks/use-scheduled-job-clock";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { currentQueryParameter, replaceCurrentQuery } from "@/lib/url-query";
import type { JobsPageProps } from "@/types/jobs";

function JobsIndex({ horizon, type, pendingCounts, jobs }: JobsPageProps) {
  return (
    <>
      <Head title="Jobs" />
      <JobsContent
        key={type}
        horizon={horizon}
        type={type}
        pendingCounts={pendingCounts}
        jobs={jobs}
      />
    </>
  );
}

function JobsContent({ horizon, type, pendingCounts, jobs }: JobsPageProps) {
  const page = usePage();
  const [search, setSearch] = useState("");
  const [hasClientSort, setHasClientSort] = useState(() => currentQueryParameter("sort") !== null);
  const deferredSearch = useDeferredValue(search);
  const jobItemsRef = useRef<HTMLTableSectionElement>(null);
  const { autoLoad } = useAutoLoadPreference();
  const refreshedJobs = useAutoLoad({
    enabled: autoLoad,
    prop: "jobs",
    interval: horizon.pollInterval,
    items: jobs.data,
    additionalProps: type === "pending" ? pendingRefreshProps : undefined,
    scope: type,
  });
  const now = useScheduledJobClock(refreshedJobs.items);
  const jobFilters = useJobFilters(type, refreshedJobs.items, now);
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
  const visibleJobIds = useMemo(() => new Set(visibleJobs.map((job) => job.id)), [visibleJobs]);
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

  useEffect(() => {
    if (
      !hasClientSort ||
      currentQueryParameter("sort") === null ||
      currentQueryParameter("starting_at") === null
    ) {
      return;
    }

    replaceCurrentQuery({ starting_at: null });
  }, [hasClientSort, page.url]);

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
          onClearFilters={jobFilters.clearAllFilters}
          description={`Narrow the loaded ${type} jobs using filters available for this tab.`}
        />
      }
      actions={
        type === "pending" ? (
          <PendingJobsActions
            horizonBaseUrl={horizon.baseUrl}
            counts={{
              ready: pendingCounts?.ready ?? null,
              delayed: pendingCounts?.delayed ?? null,
            }}
            disabled={!jobs.available || pendingCounts?.available !== true}
          />
        ) : null
      }
    >
      <InfiniteScroll
        key={`${type}:${jobs.available}`}
        data="jobs"
        itemsElement={jobItemsRef}
        onlyNext
        preserveUrl={hasClientSort}
        buffer={600}
        loading={<LoadingRows />}
      >
        <JobTable
          jobs={refreshedJobs.items}
          type={type}
          horizonBaseUrl={horizon.baseUrl}
          available={jobs.available}
          message={jobs.message}
          hasNewEntries={refreshedJobs.hasNewEntries}
          onLoadNewEntries={refreshedJobs.loadNewEntries}
          emptyTitle={hasSearch || hasFilters ? `No matching ${type} jobs` : undefined}
          emptyDescription={emptyDescription}
          visibleJobIds={visibleJobIds}
          onSortedChange={setHasClientSort}
          bodyRef={jobItemsRef}
        />
      </InfiniteScroll>
    </JobsPage>
  );
}

const pendingRefreshProps = ["pendingCounts"];

function LoadingRows() {
  return (
    <div className="flex flex-col gap-2 border-t p-4" aria-label="Loading more jobs">
      <Skeleton className="h-9 w-full" />
      <Skeleton className="h-9 w-full" />
    </div>
  );
}

export default JobsIndex;
