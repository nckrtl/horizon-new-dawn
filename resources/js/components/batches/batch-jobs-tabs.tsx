import { useLayoutEffect, useRef } from "react";

import { BatchFailedJobsActions } from "@/components/batches/batch-actions";
import { BatchFailedJobsTable } from "@/components/batches/batch-failed-jobs-table";
import { JobTable } from "@/components/jobs/job-table";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import type { BatchJobList, BatchJobTab } from "@/types/batches";

const tabs: { value: BatchJobTab; label: string; shortLabel: string }[] = [
  { value: "pending", label: "Pending Jobs", shortLabel: "Pending" },
  { value: "completed", label: "Completed Jobs", shortLabel: "Completed" },
  { value: "failed", label: "Failed Jobs", shortLabel: "Failed" },
];

export function BatchJobsTabs({
  value,
  onValueChange,
  jobs,
  horizonBaseUrl,
  batchId,
}: {
  value: BatchJobTab;
  onValueChange: (value: BatchJobTab) => void;
  jobs: Record<BatchJobTab, BatchJobList>;
  horizonBaseUrl: string;
  batchId: string;
}) {
  const tabsListRef = useRef<HTMLDivElement>(null);

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
  }, [value]);

  return (
    <Tabs
      value={value}
      onValueChange={(next: string) => onValueChange(next as BatchJobTab)}
      className="gap-0"
    >
      <div className="flex items-center border-b border-separator pr-6">
        <TabsList
          ref={tabsListRef}
          variant="line"
          aria-label="Batch jobs"
          className="min-w-0 flex-1 justify-start gap-2 overflow-x-auto rounded-none px-3 py-0"
        >
          {tabs
            .filter((tab) => tab.value !== "pending" || jobs.pending.total > 0)
            .map((tab) => (
              <TabsTrigger
                className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
                value={tab.value}
                key={tab.value}
                aria-label={`${tab.shortLabel} ${jobs[tab.value].total}`}
              >
                <span>
                  <span>{tab.shortLabel}</span> <span>Jobs</span>
                </span>
                <Badge className="h-4 min-w-4 px-1.5 text-[10.5px]" variant="secondary">
                  {jobs[tab.value].total}
                </Badge>
              </TabsTrigger>
            ))}
        </TabsList>

        {value === "failed" ? (
          <BatchFailedJobsActions
            batchId={batchId}
            horizonBaseUrl={horizonBaseUrl}
            disabled={jobs.failed.total === 0}
          />
        ) : null}
      </div>

      <h2 className="sr-only">{tabs.find((tab) => tab.value === value)?.label}</h2>

      <BatchJobTabContent status="pending" list={jobs.pending} horizonBaseUrl={horizonBaseUrl} />
      <BatchJobTabContent
        status="completed"
        list={jobs.completed}
        horizonBaseUrl={horizonBaseUrl}
      />
      <BatchJobTabContent status="failed" list={jobs.failed} horizonBaseUrl={horizonBaseUrl} />
    </Tabs>
  );
}

function BatchJobTabContent({
  status,
  list,
  horizonBaseUrl,
}: {
  status: BatchJobTab;
  list: BatchJobList;
  horizonBaseUrl: string;
}) {
  const emptyTitle =
    list.total > 0 && list.rows.length === 0 && !list.complete
      ? `No retained ${status} jobs`
      : `No ${status} jobs`;
  const emptyDescription =
    list.total > 0 && list.rows.length === 0 && !list.complete
      ? `Horizon has already trimmed the retained details for these ${status} jobs.`
      : `This batch has no ${status} jobs.`;
  const notice = list.available && !list.complete ? list.message : null;

  return (
    <TabsContent value={status} className="mt-0">
      {status === "failed" ? (
        <BatchFailedJobsTable
          jobs={list.rows}
          horizonBaseUrl={horizonBaseUrl}
          available={list.available}
          message={list.message}
          notice={notice}
          emptyTitle={emptyTitle}
          emptyDescription={emptyDescription}
        />
      ) : (
        <JobTable
          jobs={list.rows}
          type={status}
          horizonBaseUrl={horizonBaseUrl}
          available={list.available}
          message={list.message}
          notice={notice}
          emptyTitle={emptyTitle}
          emptyDescription={emptyDescription}
        />
      )}
    </TabsContent>
  );
}
