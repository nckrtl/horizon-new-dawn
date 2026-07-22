import { router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";

import { TableNoticeRow } from "@/components/data-table/new-entries-alert";
import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { TableEmpty } from "@/components/data-table/table-empty";
import { JobTablePrimaryCell } from "@/components/jobs/job-table-primary-cell";
import { BatchesNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { show as failedJobShow } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import type { JobRow } from "@/types/jobs";

const columns: SortColumn<JobRow>[] = [
  { key: "name", value: (job) => job.name },
  { key: "attempts", value: (job) => job.attempts },
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

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

export function BatchFailedJobsTable({
  jobs,
  horizonBaseUrl,
  available = true,
  message = null,
  notice = null,
  emptyTitle = "No failed jobs",
  emptyDescription = "There aren't any failed jobs in this batch.",
}: {
  jobs: readonly JobRow[];
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
  notice?: string | null;
  emptyTitle?: string;
  emptyDescription?: string;
}) {
  const sorted = useSortableRows(jobs, columns, { persist: true });

  if (!available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Failed jobs unavailable</AlertTitle>
        <AlertDescription>
          {message ?? "Failed jobs for this batch are currently unavailable."}
        </AlertDescription>
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
          <SortableTableHead
            label="Attempts"
            columnKey="attempts"
            direction={directionFor("attempts", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[120px] px-6 text-right"
          />
          <SortableTableHead
            label="Failed At"
            columnKey="failedAt"
            direction={directionFor("failedAt", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[210px] px-6"
          />
        </TableRow>
      </TableHeader>
      <TableBody>
        {notice && sorted.rows.length > 0 ? (
          <TableNoticeRow columns={3}>{notice}</TableNoticeRow>
        ) : null}
        {sorted.rows.length === 0 ? (
          <TableEmpty
            columns={3}
            title={emptyTitle}
            description={emptyDescription}
            icon={BatchesNavigationIcon}
          />
        ) : null}
        {sorted.rows.map((job) => {
          const detailUrl = resolveHorizonRoute(failedJobShow(job.id), horizonBaseUrl).url;

          return (
            <TableRow
              className="cursor-pointer"
              key={job.id}
              data-test="batch-failed-job-row"
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
                details={<span>{job.id}</span>}
              />
              <TableCell
                className="px-6 text-right tabular-nums text-muted-foreground"
                data-test="batch-failed-attempts"
              >
                {job.attemptsComplete !== false ? (
                  job.attempts
                ) : (
                  <Tooltip>
                    <TooltipTrigger render={<span className="cursor-help" />}>
                      {job.attempts}+
                    </TooltipTrigger>
                    <TooltipContent>
                      Earlier attempts were trimmed by Horizon. At least {job.attempts} attempts are
                      known.
                    </TooltipContent>
                  </Tooltip>
                )}
              </TableCell>
              <TableCell className="px-6 text-muted-foreground">
                {job.failedAt === null ? "—" : dateFormatter.format(job.failedAt * 1000)}
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
