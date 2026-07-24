import { router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";
import { useEffect, useMemo, type Ref } from "react";

import { NewEntriesTableRow } from "@/components/data-table/new-entries-alert";
import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { TableEmpty } from "@/components/data-table/table-empty";
import { Duration } from "@/components/duration";
import { FailedJobActionsMenu } from "@/components/jobs/failed-job-actions";
import { JobTablePrimaryCell } from "@/components/jobs/job-table-primary-cell";
import { MonitoringNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "@/components/ui/table";
import { show as failedJobShow } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { show as jobShow } from "@/generated/routes/horizon-new-dawn/jobs";
import { useScheduledJobClock } from "@/hooks/use-scheduled-job-clock";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import { pendingJobState } from "@/lib/pending-job-state";
import type { JobRow } from "@/types/jobs";
import type { MonitoringStatus } from "@/types/monitoring";

const columns: SortColumn<JobRow>[] = [
  { key: "name", value: (job) => job.name },
  { key: "pushedAt", value: (job) => job.pushedAt },
  { key: "completedAt", value: (job) => job.completedAt },
  { key: "failedAt", value: (job) => job.failedAt },
  { key: "runtime", value: (job) => job.runtime },
];
const dateFormatter = new Intl.DateTimeFormat("sv-SE", {
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
  hour12: false,
});

function formatTimestamp(timestamp: number | null) {
  return timestamp === null ? "—" : dateFormatter.format(timestamp * 1000);
}

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

export function MonitoringJobTable({
  jobs,
  status,
  horizonBaseUrl,
  available = true,
  message = null,
  hasNewEntries = false,
  onLoadNewEntries,
  jobFilter = null,
  queueFilter = null,
  onSortedChange,
  bodyRef,
}: {
  jobs: readonly JobRow[];
  status: MonitoringStatus;
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
  hasNewEntries?: boolean;
  onLoadNewEntries?: () => void;
  jobFilter?: string | null;
  queueFilter?: string | null;
  onSortedChange?: (sorted: boolean) => void;
  bodyRef?: Ref<HTMLTableSectionElement>;
}) {
  const now = useScheduledJobClock(jobs);
  const sorted = useSortableRows(jobs, columns, { persist: true });
  const failed = status === "failed";
  const isSorted = sorted.sort !== null;

  const visibleJobIds = useMemo(() => {
    return new Set(
      jobs
        .filter((row) => {
          if (queueFilter !== null && row.queue !== queueFilter) {
            return false;
          }

          if (jobFilter !== null && row.name !== jobFilter) {
            return false;
          }

          return true;
        })
        .map((row) => row.id),
    );
  }, [jobFilter, jobs, queueFilter]);
  const hasVisibleJobs = visibleJobIds.size > 0;
  const renderedRows = [
    ...sorted.rows.filter((job) => visibleJobIds.has(job.id)),
    ...sorted.rows.filter((job) => !visibleJobIds.has(job.id)),
  ];

  useEffect(() => {
    onSortedChange?.(isSorted);
  }, [isSorted, onSortedChange]);

  if (!available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Tagged jobs unavailable</AlertTitle>
        <AlertDescription>
          {message ?? "Jobs for this tag are currently unavailable."}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <>
      <Table>
        <TableHeader className="sticky top-0 z-10">
          <TableRow>
            <SortableTableHead
              label="Job"
              columnKey="name"
              direction={directionFor("name", sorted.sort)}
              onSort={sorted.toggle}
              className="px-6"
            />
            <SortableTableHead
              label="Queued At"
              columnKey="pushedAt"
              direction={directionFor("pushedAt", sorted.sort)}
              onSort={sorted.toggle}
              className="w-[210px] px-6"
            />
            {failed ? (
              <SortableTableHead
                label="Failed At"
                columnKey="failedAt"
                direction={directionFor("failedAt", sorted.sort)}
                onSort={sorted.toggle}
                className="w-[210px] px-6"
              />
            ) : (
              <SortableTableHead
                label="Runtime"
                columnKey="runtime"
                direction={directionFor("runtime", sorted.sort)}
                onSort={sorted.toggle}
                className="w-[110px] px-6 text-right"
              />
            )}
            {failed ? (
              <SortableTableHead label="Actions" className="w-[80px] px-6 text-right" />
            ) : null}
          </TableRow>
        </TableHeader>
        <TableBody role="presentation">
          {hasNewEntries && onLoadNewEntries ? (
            <NewEntriesTableRow columns={failed ? 4 : 3} onLoad={onLoadNewEntries} />
          ) : null}
          {!hasVisibleJobs ? (
            <TableEmpty
              columns={failed ? 4 : 3}
              title={jobs.length === 0 ? "No jobs for this tag" : "No matching jobs"}
              description={
                jobs.length === 0
                  ? "There aren't any jobs for this tag yet. Monitored history starts after jobs are processed with this exact tag."
                  : "No loaded jobs match the current filters."
              }
              icon={MonitoringNavigationIcon}
            />
          ) : null}
        </TableBody>
        <TableBody ref={bodyRef}>
          {renderedRows.map((job) => {
            const failedDetail = failed || job.status === "failed";
            const detailUrl = failedDetail
              ? resolveHorizonRoute(failedJobShow(job.id), horizonBaseUrl).url
              : resolveHorizonRoute(
                  jobShow({
                    type: job.status === "completed" ? "completed" : "pending",
                    job: job.id,
                  }),
                  horizonBaseUrl,
                ).url;
            const delayed = job.status === "pending" && pendingJobState(job, now) === "delayed";

            return (
              <TableRow
                className="cursor-pointer"
                key={job.id}
                hidden={!visibleJobIds.has(job.id)}
                onClick={(event) => {
                  if (isInteractiveTarget(event.target)) {
                    return;
                  }

                  router.visit(detailUrl);
                }}
                onMouseEnter={() => router.prefetch(detailUrl)}
              >
                <JobTablePrimaryCell
                  name={job.shortName}
                  fullName={job.name}
                  queue={job.queue}
                  tags={job.tags}
                  href={detailUrl}
                  tagLimit={3}
                  accessory={delayed ? <Badge variant="delayed">Delayed</Badge> : undefined}
                />
                <TableCell className="px-6 text-muted-foreground">
                  {formatTimestamp(job.pushedAt)}
                </TableCell>
                {failed ? (
                  <TableCell className="px-6 text-muted-foreground">
                    {formatTimestamp(job.failedAt)}
                  </TableCell>
                ) : (
                  <TableCell className="px-6 text-right text-muted-foreground">
                    {job.runtime === null ? (
                      "—"
                    ) : (
                      <Duration seconds={job.runtime} format="precise" />
                    )}
                  </TableCell>
                )}
                {failed ? (
                  <TableCell className="px-6 text-right">
                    <FailedJobActionsMenu jobId={job.id} horizonBaseUrl={horizonBaseUrl} canRetry />
                  </TableCell>
                ) : null}
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </>
  );
}
