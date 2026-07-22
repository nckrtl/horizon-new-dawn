import { Link, router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { TableEmpty } from "@/components/data-table/table-empty";
import { MonitoringActionsMenu } from "@/components/monitoring/monitoring-actions-menu";
import { MonitoringNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "@/components/ui/table";
import { show as monitoringShow } from "@/generated/routes/horizon-new-dawn/monitoring";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import type { MonitoredTag } from "@/types/monitoring";

const columns: SortColumn<MonitoredTag>[] = [
  { key: "tag", value: (item) => item.tag },
  { key: "trackedCount", value: (item) => item.trackedCount },
  { key: "failedCount", value: (item) => item.failedCount },
  { key: "lastActivityAt", value: (item) => item.lastActivityAt },
];
const numberFormatter = new Intl.NumberFormat();
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

function formatTimestamp(timestamp: number | null) {
  return timestamp === null ? "—" : dateFormatter.format(timestamp * 1000);
}

function detailUrl(tag: string, status: "jobs" | "failed", horizonBaseUrl: string) {
  return resolveHorizonRoute(
    monitoringShow({ tag: encodeURIComponent(tag), status }),
    horizonBaseUrl,
  ).url;
}

export function MonitoredTagsTable({
  tags,
  horizonBaseUrl,
  available = true,
  message = null,
}: {
  tags: readonly MonitoredTag[];
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
}) {
  const sorted = useSortableRows(tags, columns, { persist: true });

  if (!available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Monitoring unavailable</AlertTitle>
        <AlertDescription>
          {message ?? "Monitored tags are currently unavailable."}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <SortableTableHead
            label="Tag"
            columnKey="tag"
            direction={directionFor("tag", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6"
          />
          <SortableTableHead
            label="Tracked jobs"
            columnKey="trackedCount"
            direction={directionFor("trackedCount", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6 text-right"
          />
          <SortableTableHead
            label="Failed"
            columnKey="failedCount"
            direction={directionFor("failedCount", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6 text-right"
          />
          <SortableTableHead
            label="Last activity"
            columnKey="lastActivityAt"
            direction={directionFor("lastActivityAt", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6"
          />
          <SortableTableHead label="Actions" className="px-6 text-right" />
        </TableRow>
      </TableHeader>
      <TableBody>
        {sorted.rows.length === 0 ? (
          <TableEmpty
            columns={5}
            title="No monitored tags"
            description="exact tag matches"
            icon={MonitoringNavigationIcon}
          />
        ) : null}
        {sorted.rows.map((item) => {
          const jobsUrl = detailUrl(item.tag, "jobs", horizonBaseUrl);
          const failedUrl = detailUrl(item.tag, "failed", horizonBaseUrl);

          return (
            <TableRow
              className="cursor-pointer"
              key={item.tag}
              onClick={(event) => {
                if (isInteractiveTarget(event.target)) {
                  return;
                }

                router.visit(jobsUrl);
              }}
              onMouseEnter={() => router.prefetch(jobsUrl)}
            >
              <TableCell className="px-6">
                <div className="flex min-w-0 flex-wrap items-center gap-2">
                  <Link className="text-foreground" href={jobsUrl} prefetch>
                    {item.tag}
                  </Link>
                  {item.silenced ? (
                    <Badge variant="secondary" className="h-auto px-2 py-px text-[11px]">
                      Silenced
                    </Badge>
                  ) : null}
                </div>
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums">
                <Link
                  className="text-muted-foreground hover:text-foreground"
                  href={jobsUrl}
                  prefetch
                >
                  {numberFormatter.format(item.trackedCount)}
                </Link>
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums">
                <Link
                  className="text-muted-foreground hover:text-foreground"
                  href={failedUrl}
                  prefetch
                >
                  {numberFormatter.format(item.failedCount)}
                </Link>
              </TableCell>
              <TableCell className="px-6 text-muted-foreground">
                {formatTimestamp(item.lastActivityAt)}
              </TableCell>
              <TableCell className="px-6 text-right">
                <MonitoringActionsMenu
                  tag={item.tag}
                  horizonBaseUrl={horizonBaseUrl}
                  trackedCount={item.trackedCount}
                  failedCount={item.failedCount}
                />
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
