import { Link, router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";
import { useMemo } from "react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { NewEntriesTableRow, TableNoticeRow } from "@/components/data-table/new-entries-alert";
import { TableEmpty } from "@/components/data-table/table-empty";
import { JobTablePrimaryCell } from "@/components/jobs/job-table-primary-cell";
import { PendingJobActionsMenu } from "@/components/jobs/pending-job-actions";
import {
  CompletedJobsNavigationIcon,
  PendingJobsNavigationIcon,
  SilencedJobsNavigationIcon,
} from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { show as failedJobShow } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { show as jobShow } from "@/generated/routes/horizon-new-dawn/jobs";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { useScheduledJobClock } from "@/hooks/use-scheduled-job-clock";
import { formatDuration, lowercaseFirst } from "@/lib/format-duration";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import { pendingJobState, type PendingJobState } from "@/lib/pending-job-state";
import { cn } from "@/lib/utils";
import type { JobListType, JobRow } from "@/types/jobs";

const emptyStateIcons = {
  pending: PendingJobsNavigationIcon,
  completed: CompletedJobsNavigationIcon,
  silenced: SilencedJobsNavigationIcon,
} satisfies Record<JobListType, typeof PendingJobsNavigationIcon>;
const pendingStates = {
  ready: {
    label: "Ready",
    variant: "secondary",
    description: "Waiting for a worker to pick it up.",
  },
  reserved: {
    label: "Reserved",
    variant: "processing",
    description: "Reserved by a worker and awaiting processing.",
  },
  delayed: { label: "Delayed", variant: "delayed" },
  released: {
    label: "Released",
    variant: "success",
    description: "Its scheduled release time has passed and it is waiting for a worker.",
  },
} as const;
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

function pendingStateDescription(job: JobRow, state: PendingJobState, now: number) {
  if (state === "delayed") {
    return job.scheduledAt === null
      ? "Scheduled to run later."
      : `Scheduled to run in ${lowercaseFirst(
          formatDuration(Math.max(0, Math.ceil(job.scheduledAt - now))),
        )}.`;
  }

  return pendingStates[state].description;
}

export function JobTable({
  jobs,
  type,
  horizonBaseUrl,
  available = true,
  message = null,
  notice = null,
  hasNewEntries = false,
  onLoadNewEntries,
  emptyTitle,
  emptyDescription,
}: {
  jobs: readonly JobRow[];
  type: JobListType;
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
  notice?: string | null;
  hasNewEntries?: boolean;
  onLoadNewEntries?: () => void;
  emptyTitle?: string;
  emptyDescription?: string;
}) {
  const now = useScheduledJobClock(jobs);
  const columns = useMemo<SortColumn<JobRow>[]>(
    () => [
      { key: "name", value: (job) => job.name },
      { key: "status", value: (job) => pendingJobState(job, now) },
      { key: "pushedAt", value: (job) => job.pushedAt },
      { key: "completedAt", value: (job) => job.completedAt },
      { key: "runtime", value: (job) => job.runtime },
    ],
    [now],
  );
  const sorted = useSortableRows(jobs, columns, { persist: true });
  const compact = type === "pending";

  if (!available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Jobs unavailable</AlertTitle>
        <AlertDescription>{message ?? "Horizon jobs are currently unavailable."}</AlertDescription>
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
            className="px-6"
          />
          {compact ? (
            <SortableTableHead
              label="State"
              columnKey="status"
              direction={directionFor("status", sorted.sort)}
              onSort={sorted.toggle}
              className="w-[130px] px-6"
            />
          ) : null}
          <SortableTableHead
            label="Queued"
            columnKey="pushedAt"
            direction={directionFor("pushedAt", sorted.sort)}
            onSort={sorted.toggle}
            className={cn("w-[210px] px-6", compact && "text-right")}
          />
          {!compact ? (
            <SortableTableHead
              label="Completed"
              columnKey="completedAt"
              direction={directionFor("completedAt", sorted.sort)}
              onSort={sorted.toggle}
              className="w-[210px] px-6"
            />
          ) : null}
          {!compact ? (
            <SortableTableHead
              label="Runtime"
              columnKey="runtime"
              direction={directionFor("runtime", sorted.sort)}
              onSort={sorted.toggle}
              className="w-[110px] px-6 text-right"
            />
          ) : null}
          {compact ? <TableHead className="w-[72px] px-6 text-right">Actions</TableHead> : null}
        </TableRow>
      </TableHeader>
      <TableBody>
        {hasNewEntries && onLoadNewEntries ? (
          <NewEntriesTableRow columns={4} onLoad={onLoadNewEntries} />
        ) : null}
        {notice && sorted.rows.length > 0 ? (
          <TableNoticeRow columns={4}>{notice}</TableNoticeRow>
        ) : null}
        {sorted.rows.length === 0 ? (
          <TableEmpty
            columns={4}
            title={emptyTitle ?? `No ${type} jobs`}
            description={emptyDescription ?? `Horizon is not reporting any ${type} jobs.`}
            icon={emptyStateIcons[type]}
          />
        ) : null}
        {sorted.rows.map((job) => {
          const detailUrl = resolveHorizonRoute(jobShow({ type, job: job.id }), horizonBaseUrl).url;
          const retryOfUrl = job.retryOf
            ? resolveHorizonRoute(failedJobShow(job.retryOf), horizonBaseUrl).url
            : null;
          const state = pendingJobState(job, now);
          const pendingStateDetails = pendingStates[state];

          return (
            <TableRow
              className="cursor-pointer"
              key={job.id}
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
                accessory={job.retryOf ? <Badge variant="retry">Retry</Badge> : undefined}
                details={
                  retryOfUrl ? (
                    <span>
                      Retry of{" "}
                      <Link className="text-foreground hover:underline" href={retryOfUrl} prefetch>
                        {job.retryOf}
                      </Link>
                    </span>
                  ) : undefined
                }
              />
              {compact ? (
                <TableCell className="px-6">
                  <Tooltip>
                    <TooltipTrigger render={<span className="inline-flex" />}>
                      <Badge variant={pendingStateDetails.variant}>
                        {pendingStateDetails.label}
                      </Badge>
                    </TooltipTrigger>
                    <TooltipContent>{pendingStateDescription(job, state, now)}</TooltipContent>
                  </Tooltip>
                </TableCell>
              ) : null}
              <TableCell className={cn("px-6 text-muted-foreground", compact && "text-right")}>
                {formatTimestamp(job.pushedAt)}
              </TableCell>
              {compact ? (
                <TableCell className="px-6 text-right">
                  {state !== "reserved" ? (
                    <PendingJobActionsMenu
                      jobId={job.id}
                      horizonBaseUrl={horizonBaseUrl}
                      canRelease={state === "delayed"}
                    />
                  ) : null}
                </TableCell>
              ) : null}
              {!compact ? (
                <TableCell className="px-6 text-muted-foreground">
                  {formatTimestamp(job.completedAt)}
                </TableCell>
              ) : null}
              {!compact ? (
                <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                  {job.runtime === null ? "—" : `${job.runtime.toFixed(2)}s`}
                </TableCell>
              ) : null}
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
