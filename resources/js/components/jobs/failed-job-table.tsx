import { Link, router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";
import { useEffect, type Ref } from "react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { NewEntriesTableRow } from "@/components/data-table/new-entries-alert";
import { TableEmpty } from "@/components/data-table/table-empty";
import { Duration } from "@/components/duration";
import { FailedJobActionsMenu } from "@/components/jobs/failed-job-actions";
import { JobTablePrimaryCell } from "@/components/jobs/job-table-primary-cell";
import { FailedJobsNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { show as failedJobShow } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import type { JobRow } from "@/types/jobs";

const columns: SortColumn<JobRow>[] = [
  { key: "name", value: (job) => job.name },
  { key: "runtime", value: (job) => job.runtime },
  { key: "failedAt", value: (job) => job.failedAt },
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

function upperFirst(value: string | null) {
  return value ? `${value.charAt(0).toUpperCase()}${value.slice(1)}` : "Unknown";
}

function retryStatus(status: string | null) {
  if (status === "pending" || status === "reserved") {
    return { label: "Retry queued", variant: "retry" } as const;
  }

  if (status === "completed") {
    return { label: "Retried · Completed", variant: "success" } as const;
  }

  if (status === "failed") {
    return { label: "Retried · Failed", variant: "destructive" } as const;
  }

  return { label: "Retried · Unknown", variant: "warning" } as const;
}

export function FailedJobTable({
  jobs,
  horizonBaseUrl,
  available = true,
  message = null,
  hasNewEntries = false,
  onLoadNewEntries,
  emptyTitle,
  emptyDescription,
  visibleJobIds,
  onSortedChange,
  bodyRef,
}: {
  jobs: readonly JobRow[];
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
  hasNewEntries?: boolean;
  onLoadNewEntries?: () => void;
  emptyTitle?: string;
  emptyDescription?: string;
  visibleJobIds?: ReadonlySet<string>;
  onSortedChange?: (sorted: boolean) => void;
  bodyRef?: Ref<HTMLTableSectionElement>;
}) {
  const sorted = useSortableRows(jobs, columns, { persist: true });
  const isSorted = sorted.sort !== null;
  const hasVisibleJobs =
    visibleJobIds === undefined
      ? sorted.rows.length > 0
      : sorted.rows.some((job) => visibleJobIds.has(job.id));
  const renderedRows =
    visibleJobIds === undefined
      ? sorted.rows
      : [
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
        <AlertTitle>Failed jobs unavailable</AlertTitle>
        <AlertDescription>{message ?? "Failed jobs are currently unavailable."}</AlertDescription>
      </Alert>
    );
  }

  return (
    <Table>
      <TableHeader className="sticky top-0 z-10">
        <TableRow>
          <SortableTableHead
            label="Job"
            columnKey="name"
            direction={directionFor("name", sorted.sort)}
            onSort={sorted.toggle}
            className="px-4 sm:px-6"
          />
          <SortableTableHead
            label="Runtime"
            columnKey="runtime"
            direction={directionFor("runtime", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[100px] px-4 text-right sm:px-6"
          />
          <SortableTableHead
            label="Failed"
            columnKey="failedAt"
            direction={directionFor("failedAt", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[190px] px-4 sm:px-6"
          />
          <SortableTableHead label="Actions" className="w-[80px] px-4 text-right sm:px-6" />
        </TableRow>
      </TableHeader>
      <TableBody role="presentation">
        {hasNewEntries && onLoadNewEntries ? (
          <NewEntriesTableRow columns={4} onLoad={onLoadNewEntries} />
        ) : null}
        {!hasVisibleJobs ? (
          <TableEmpty
            columns={4}
            title={emptyTitle ?? "No failed jobs"}
            description={emptyDescription ?? "There aren't any failed jobs."}
            icon={FailedJobsNavigationIcon}
          />
        ) : null}
      </TableBody>
      <TableBody ref={bodyRef}>
        {renderedRows.map((job) => {
          const detailUrl = resolveHorizonRoute(failedJobShow(job.id), horizonBaseUrl).url;
          const retryOfUrl = job.retryOf
            ? resolveHorizonRoute(failedJobShow(job.retryOf), horizonBaseUrl).url
            : null;
          const latestRetry = retryStatus(job.latestRetryStatus);

          return (
            <TableRow
              className="cursor-pointer"
              key={job.id}
              hidden={visibleJobIds !== undefined && !visibleJobIds.has(job.id)}
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
                className="px-4 sm:px-6"
                accessory={
                  job.retried ? (
                    <Tooltip>
                      <TooltipTrigger
                        render={
                          <button
                            type="button"
                            className="inline-flex items-center gap-1 text-[11.5px] font-medium text-muted-foreground"
                            title={`Total retries: ${job.retryCount}, Last retry status: ${upperFirst(job.latestRetryStatus)}`}
                          />
                        }
                      >
                        <Badge variant={latestRetry.variant}>{latestRetry.label}</Badge>
                      </TooltipTrigger>
                      <TooltipContent>
                        Total retries: {job.retryCount}, Last retry status:{" "}
                        {upperFirst(job.latestRetryStatus)}
                      </TooltipContent>
                    </Tooltip>
                  ) : undefined
                }
                details={
                  <>
                    <span>Attempts: {job.attempts}</span>
                    {retryOfUrl ? (
                      <span>
                        Retry of{" "}
                        <Link href={retryOfUrl} prefetch>
                          {job.retryOf}
                        </Link>
                      </span>
                    ) : null}
                  </>
                }
              />
              <TableCell className="px-4 text-right tabular-nums text-muted-foreground sm:px-6">
                {job.runtime === null ? "—" : <Duration seconds={job.runtime} format="precise" />}
              </TableCell>
              <TableCell className="px-4 text-muted-foreground sm:px-6">
                {formatTimestamp(job.failedAt)}
              </TableCell>
              <TableCell className="px-4 text-right sm:px-6">
                <FailedJobActionsMenu
                  jobId={job.id}
                  horizonBaseUrl={horizonBaseUrl}
                  canRetry={job.retryEligible}
                />
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
