import { InfiniteScroll, Link, router } from "@inertiajs/react";
import { HistoryIcon, SearchIcon, XIcon } from "lucide-react";
import { useDeferredValue, useEffect, useLayoutEffect, useMemo, useRef, useState } from "react";

import { BatchFilters } from "@/components/batches/batch-filters";
import { BatchTable } from "@/components/batches/batch-table";
import { QueueBatchesActions } from "@/components/batches/queue-batches-actions";
import { FailedJobTable } from "@/components/jobs/failed-job-table";
import { JobFilters } from "@/components/jobs/job-filters";
import { JobTable } from "@/components/jobs/job-table";
import { PendingJobsActions } from "@/components/jobs/pending-jobs-actions";
import { QueueActionsMenu } from "@/components/queues/queue-actions-menu";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { show as queueShow } from "@/generated/routes/horizon-new-dawn/queues";
import { useJobFilters } from "@/hooks/use-job-filters";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { BatchRow, BatchStatus } from "@/types/batches";
import type { JobRow } from "@/types/jobs";
import type {
  QueueActivity,
  QueueActivityTab,
  QueueDetailView,
  QueueSummary,
} from "@/types/queues";

type QueueActivityRow = JobRow | BatchRow;

const tabs: Array<{ value: QueueActivityTab; label: string }> = [
  { value: "pending", label: "Pending Jobs" },
  { value: "completed", label: "Completed Jobs" },
  { value: "failed", label: "Failed Jobs" },
  { value: "silenced", label: "Silenced Jobs" },
  { value: "batches", label: "Batches" },
];

function tabCount(summary: QueueSummary, tab: QueueActivityTab) {
  const [value, complete] = {
    pending: [summary.pendingJobs, summary.pendingComplete],
    completed: [summary.completedJobs, summary.completedComplete],
    failed: [summary.failedJobs, summary.failedComplete],
    silenced: [summary.silencedJobs, summary.silencedComplete],
    batches: [summary.batches, summary.batchesComplete],
  }[tab];

  if (value === null) {
    return "—";
  }

  return complete ? String(value) : `${value}+`;
}

export function QueueActivityTabs({
  queue,
  tab,
  view,
  summary,
  activity,
  horizonBaseUrl,
}: {
  queue: string;
  tab: QueueActivityTab;
  view: QueueDetailView;
  summary: QueueSummary;
  activity: QueueActivity;
  horizonBaseUrl: string;
}) {
  const tabsListRef = useRef<HTMLDivElement>(null);
  const [search, setSearch] = useState("");
  const deferredSearch = useDeferredValue(search);
  const [batchStatus, setBatchStatus] = useState<BatchStatus | null>(null);
  const [stableRows, setStableRows] = useState<QueueActivityRow[]>(activity.data);
  const jobRows = tab === "batches" ? [] : (stableRows as JobRow[]);
  const batchRows = tab === "batches" ? (stableRows as BatchRow[]) : [];
  const jobFilters = useJobFilters(tab === "batches" ? "completed" : tab, jobRows);
  const normalizedSearch = deferredSearch.trim().toLocaleLowerCase();
  const visibleJobs = useMemo(() => {
    if (normalizedSearch === "") {
      return jobFilters.filteredJobs;
    }

    return jobFilters.filteredJobs.filter((job) =>
      [job.id, job.name, job.shortName, job.connection, job.queue, ...job.tags]
        .join(" ")
        .toLocaleLowerCase()
        .includes(normalizedSearch),
    );
  }, [jobFilters.filteredJobs, normalizedSearch]);
  const visibleBatches = useMemo(
    () =>
      batchRows.filter((batch) => {
        const matchesSearch =
          normalizedSearch === "" ||
          [batch.id, batch.name, batch.displayName]
            .filter((value): value is string => value !== null)
            .join(" ")
            .toLocaleLowerCase()
            .includes(normalizedSearch);

        return matchesSearch && (batchStatus === null || batch.status === batchStatus);
      }),
    [batchRows, batchStatus, normalizedSearch],
  );
  const hasSearch = normalizedSearch !== "";
  const hasFilters = tab === "batches" ? batchStatus !== null : jobFilters.activeFilterCount > 0;
  const resultName = tab === "batches" ? "batches" : `${tab} jobs`;
  const emptyTitle = `No retained ${tab === "batches" ? "batches" : `${tab} jobs`}`;
  const emptyDescription = activity.complete
    ? `Horizon is not retaining any ${tab === "batches" ? "batches" : `${tab} jobs`} for this queue.`
    : "No matching entries were found in the inspected history. More retained entries may exist.";
  const batchFailedJobs =
    tab === "batches" ? batchRows.reduce((total, batch) => total + batch.failedJobs, 0) : 0;

  useEffect(() => {
    setStableRows((current) => reconcileActivityRows(current, activity.data, activity.complete));
  }, [activity.complete, activity.data]);

  useLayoutEffect(() => {
    const list = tabsListRef.current;
    const activeTab = list?.querySelector<HTMLElement>("[data-active]");

    if (!list || !activeTab) {
      return;
    }

    const listBounds = list.getBoundingClientRect();
    const tabBounds = activeTab.getBoundingClientRect();

    if (tabBounds.left < listBounds.left) {
      list.scrollLeft -= listBounds.left - tabBounds.left;
    } else if (tabBounds.right > listBounds.right) {
      list.scrollLeft += tabBounds.right - listBounds.right;
    }
  }, [tab]);

  return (
    <Card id="queue-activity" tabIndex={-1} className="scroll-mt-3.5 outline-none">
      <CardContent className="p-0">
        <Tabs value={tab} className="gap-0">
          <div className="flex items-center pr-6">
            <TabsList
              ref={tabsListRef}
              variant="line"
              aria-label="Queue activity"
              className="min-w-0 flex-1 justify-start gap-2 overflow-x-auto rounded-none px-3 py-0"
            >
              {tabs.map((item) => {
                const route = queueShow(encodeURIComponent(queue), {
                  query: view === "metrics" ? { tab: item.value, view } : { tab: item.value },
                });
                const href = resolveHorizonRoute(route, horizonBaseUrl).url;
                const count = tabCount(summary, item.value);

                return (
                  <TabsTrigger
                    value={item.value}
                    key={item.value}
                    nativeButton={false}
                    className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
                    render={
                      <Link
                        href={href}
                        prefetch
                        aria-label={`${item.label} ${count}`}
                        onClick={(event) => {
                          event.preventDefault();
                          router.visit(href, {
                            replace: true,
                            preserveScroll: true,
                            preserveState: true,
                            only: ["summary", "activity", "tab", "view", "preview", "horizon"],
                            reset: ["activity"],
                          });
                        }}
                      />
                    }
                  >
                    {item.label}
                    <Badge className="h-4 min-w-4 px-1.5 text-[10.5px]" variant="secondary">
                      {count}
                    </Badge>
                  </TabsTrigger>
                );
              })}
            </TabsList>

            {tab === "batches" ? (
              <QueueBatchesActions
                queue={queue}
                failedJobs={batchFailedJobs}
                horizonBaseUrl={horizonBaseUrl}
              />
            ) : tab === "pending" ? (
              <PendingJobsActions
                horizonBaseUrl={horizonBaseUrl}
                queue={queue}
                counts={{ ready: summary.pendingReadyNow, delayed: summary.pendingDelayed }}
                disabled={!activity.available}
              />
            ) : tab === "failed" ? (
              <QueueActionsMenu
                queue={queue}
                targets={summary.pauseTargets}
                failedJobs={summary.failedJobs}
                horizonBaseUrl={horizonBaseUrl}
                scope={tab}
              />
            ) : null}
          </div>

          <QueueActivityToolbar
            tab={tab}
            search={search}
            onSearchChange={setSearch}
            filters={
              tab === "batches" ? (
                <BatchFilters
                  value={batchStatus}
                  onValueChange={setBatchStatus}
                  description="Narrow the loaded batches retained for this queue."
                />
              ) : (
                <JobFilters
                  jobs={jobRows}
                  filterKeys={jobFilters.availableFilterKeys}
                  values={jobFilters.values}
                  onFilterChange={jobFilters.setFilterValue}
                  description={`Narrow the loaded ${tab} jobs retained for this queue.`}
                />
              )
            }
          />

          {activity.message ? (
            <Alert className="m-4">
              <HistoryIcon aria-hidden="true" />
              <AlertTitle>Retained history is incomplete</AlertTitle>
              <AlertDescription>{activity.message}</AlertDescription>
            </Alert>
          ) : null}

          <InfiniteScroll data="activity" onlyNext buffer={600}>
            {tab === "batches" ? (
              <BatchTable
                batches={visibleBatches}
                horizonBaseUrl={horizonBaseUrl}
                available={activity.available}
                message={activity.message}
                emptyTitle={hasSearch || hasFilters ? "No matching batches" : emptyTitle}
                emptyDescription={
                  hasSearch || hasFilters
                    ? "No loaded batches match your search and filters."
                    : emptyDescription
                }
                showBatchActions
              />
            ) : tab === "failed" ? (
              <FailedJobTable
                jobs={visibleJobs}
                horizonBaseUrl={horizonBaseUrl}
                available={activity.available}
                message={activity.message}
                emptyTitle={hasSearch || hasFilters ? `No matching ${resultName}` : emptyTitle}
                emptyDescription={
                  hasSearch || hasFilters
                    ? `No loaded ${resultName} match your search and filters.`
                    : emptyDescription
                }
              />
            ) : (
              <JobTable
                jobs={visibleJobs}
                type={tab}
                horizonBaseUrl={horizonBaseUrl}
                available={activity.available}
                message={activity.message}
                emptyTitle={hasSearch || hasFilters ? `No matching ${resultName}` : emptyTitle}
                emptyDescription={
                  hasSearch || hasFilters
                    ? `No loaded ${resultName} match your search and filters.`
                    : emptyDescription
                }
              />
            )}
          </InfiniteScroll>
        </Tabs>
      </CardContent>
    </Card>
  );
}

function reconcileActivityRows(
  current: QueueActivityRow[],
  incoming: QueueActivityRow[],
  complete: boolean,
): QueueActivityRow[] {
  if (
    complete ||
    incoming.length === 0 ||
    current.length === 0 ||
    incoming.length >= current.length
  ) {
    return incoming;
  }

  const incomingIds = new Set(incoming.map((row) => row.id));

  return [...incoming, ...current.filter((row) => !incomingIds.has(row.id))].slice(
    0,
    current.length,
  );
}

function QueueActivityToolbar({
  tab,
  search,
  onSearchChange,
  filters,
}: {
  tab: QueueActivityTab;
  search: string;
  onSearchChange: (value: string) => void;
  filters: React.ReactNode;
}) {
  const resultName = tab === "batches" ? "batches" : `${tab} jobs`;
  const searchLabel = `Search ${resultName}`;

  return (
    <div className="flex min-h-10 items-center border-y border-separator px-6 py-1.5">
      <Field className="min-w-0 flex-1">
        <FieldLabel className="sr-only">{searchLabel}</FieldLabel>
        <InputGroup className="gap-1.5 border-0 bg-transparent! shadow-none has-[[data-slot=input-group-control]:focus-visible]:ring-0!">
          <InputGroupInput
            className="px-0!"
            type="text"
            role="searchbox"
            inputMode="search"
            value={search}
            aria-label={searchLabel}
            placeholder={`Search loaded ${resultName}`}
            onChange={(event) => onSearchChange(event.target.value)}
          />
          <InputGroupAddon align="inline-start" className="pl-0">
            <SearchIcon aria-hidden="true" />
          </InputGroupAddon>
          {search ? (
            <InputGroupAddon align="inline-end" className="py-0 pr-0">
              <InputGroupButton
                size="icon-xs"
                aria-label="Clear search"
                onClick={() => onSearchChange("")}
              >
                <XIcon data-icon="inline-start" />
              </InputGroupButton>
            </InputGroupAddon>
          ) : null}
        </InputGroup>
      </Field>
      <div className="flex min-h-8 shrink-0 items-center justify-end">{filters}</div>
    </div>
  );
}
