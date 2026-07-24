import { Head, InfiniteScroll, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { MonitoringActionsMenu } from "@/components/monitoring/monitoring-actions-menu";
import { MonitoringJobFilters } from "@/components/monitoring/monitoring-job-filters";
import { MonitoringJobTable } from "@/components/monitoring/monitoring-job-table";
import { MonitoringTabs } from "@/components/monitoring/monitoring-tabs";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { useAutoLoad } from "@/hooks/use-auto-load";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { copyToClipboard } from "@/lib/clipboard";
import { currentQueryParameter, replaceCurrentQuery } from "@/lib/url-query";
import type { MonitoringTagPageProps } from "@/types/monitoring";

const monitoringRefreshProps = ["summary"];

function formatRetention(minutes: number) {
  if (minutes <= 0) {
    return "disabled";
  }

  if (minutes % 1440 === 0) {
    const days = minutes / 1440;

    return `${days} ${days === 1 ? "day" : "days"}`;
  }

  if (minutes % 60 === 0) {
    const hours = minutes / 60;

    return `${hours} ${hours === 1 ? "hour" : "hours"}`;
  }

  return `${minutes} ${minutes === 1 ? "minute" : "minutes"}`;
}

async function copyTag(tag: string) {
  if (await copyToClipboard(tag)) {
    toast.success("Tag copied.");

    return;
  }

  toast.error("Tag could not be copied.");
}

function MonitoringShow({ horizon, tag, status, summary, jobs }: MonitoringTagPageProps) {
  const page = usePage();
  const { autoLoad } = useAutoLoadPreference();
  const [jobFilter, setJobFilter] = useState(() => currentQueryParameter("job"));
  const [queueFilter, setQueueFilter] = useState(() => currentQueryParameter("queue"));
  const [hasClientSort, setHasClientSort] = useState(() => currentQueryParameter("sort") !== null);
  const jobItemsRef = useRef<HTMLTableSectionElement>(null);
  const refreshedJobs = useAutoLoad({
    enabled: autoLoad,
    prop: "jobs",
    interval: horizon.pollInterval,
    items: jobs.data,
    additionalProps: monitoringRefreshProps,
    scope: `${tag}:${status}`,
  });
  const title = status === "failed" ? `Failed Jobs for "${tag}"` : `Recent Jobs for "${tag}"`;
  const changeJobFilter = (value: string | null) => {
    setJobFilter(value);
    replaceCurrentQuery({ job: value });
  };
  const changeQueueFilter = (value: string | null) => {
    setQueueFilter(value);
    replaceCurrentQuery({ queue: value });
  };
  const clearFilters = () => {
    setJobFilter(null);
    setQueueFilter(null);
    replaceCurrentQuery({ job: null, queue: null });
  };

  useEffect(() => {
    setJobFilter(currentQueryParameter("job"));
    setQueueFilter(currentQueryParameter("queue"));
  }, [page.url]);

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
    <>
      <Head title={title} />
      <Card>
        <CardHeader className="flex h-auto min-h-[54px] flex-row justify-between gap-4 px-6 py-0">
          <div className="flex min-w-0 flex-col justify-center gap-1 py-2.5">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <CardTitle className="min-w-0">
                <Tooltip>
                  <TooltipTrigger
                    render={
                      <button
                        type="button"
                        className="inline-flex max-w-full cursor-pointer rounded-sm text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label={`Copy tag ${tag}`}
                        onClick={() => void copyTag(tag)}
                      />
                    }
                  >
                    <span className="truncate" title={tag}>
                      {tag}
                    </span>
                  </TooltipTrigger>
                  <TooltipContent>Copy tag</TooltipContent>
                </Tooltip>
              </CardTitle>
              {summary.silenced ? (
                <Badge variant="secondary" className="h-auto px-2 py-px text-[11px]">
                  Silenced
                </Badge>
              ) : null}
            </div>
            <p className="text-[12.5px] text-muted-foreground">
              Retention: {formatRetention(summary.monitoredRetentionMinutes)} for recent jobs,{" "}
              {formatRetention(summary.failedRetentionMinutes)} for failed jobs
            </p>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <MonitoringActionsMenu
              tag={tag}
              horizonBaseUrl={horizon.baseUrl}
              trackedCount={summary.trackedCount}
              failedCount={summary.failedCount}
              redirectToIndex
            />
          </div>
        </CardHeader>
        <div className="flex items-center border-b border-separator">
          <MonitoringTabs
            tag={tag}
            status={status}
            horizonBaseUrl={horizon.baseUrl}
            trackedCount={summary.trackedCount}
            failedCount={summary.failedCount}
            jobFilter={jobFilter}
            queueFilter={queueFilter}
          />
          <div className="shrink-0 pr-6">
            <MonitoringJobFilters
              jobs={refreshedJobs.items}
              jobFilter={jobFilter}
              queueFilter={queueFilter}
              onJobFilterChange={changeJobFilter}
              onQueueFilterChange={changeQueueFilter}
              onClearFilters={clearFilters}
            />
          </div>
        </div>
        <CardContent className="p-0">
          <InfiniteScroll
            key={`${tag}:${status}:${jobs.available}`}
            data="jobs"
            itemsElement={jobItemsRef}
            onlyNext
            preserveUrl={hasClientSort}
            buffer={600}
            loading={<LoadingRows />}
          >
            <MonitoringJobTable
              jobs={refreshedJobs.items}
              status={status}
              horizonBaseUrl={horizon.baseUrl}
              available={jobs.available}
              message={jobs.message}
              hasNewEntries={refreshedJobs.hasNewEntries}
              onLoadNewEntries={refreshedJobs.loadNewEntries}
              jobFilter={jobFilter}
              queueFilter={queueFilter}
              onSortedChange={setHasClientSort}
              bodyRef={jobItemsRef}
            />
          </InfiniteScroll>
        </CardContent>
      </Card>
    </>
  );
}

function LoadingRows() {
  return (
    <div className="flex flex-col gap-2 border-t p-4" aria-label="Loading more tagged jobs">
      <Skeleton className="h-12 w-full" />
      <Skeleton className="h-12 w-full" />
    </div>
  );
}

export default MonitoringShow;
