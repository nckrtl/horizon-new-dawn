import { Link, router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";
import { useEffect, type Ref } from "react";

import { BatchPendingJobsActions } from "@/components/batches/batch-actions";
import { ProgressRing } from "@/components/batches/progress-ring";
import { NewEntriesTableRow } from "@/components/data-table/new-entries-alert";
import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { TableEmpty } from "@/components/data-table/table-empty";
import { BatchesNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { show as batchShow } from "@/generated/routes/horizon-new-dawn/batches";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import type { BatchRow } from "@/types/batches";

const columns: SortColumn<BatchRow>[] = [
  { key: "name", value: (batch) => batch.displayName },
  { key: "totalJobs", value: (batch) => batch.totalJobs },
  { key: "pendingJobs", value: (batch) => batch.pendingJobs },
  { key: "failedJobs", value: (batch) => batch.failedJobs },
  { key: "progress", value: (batch) => batch.progress },
  { key: "createdAt", value: (batch) => batch.createdAt },
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

export function BatchTable({
  batches,
  horizonBaseUrl,
  available = true,
  message = null,
  hasNewEntries = false,
  onLoadNewEntries,
  emptyTitle,
  emptyDescription,
  showBatchActions = false,
  visibleBatchIds,
  onSortedChange,
  bodyRef,
}: {
  batches: readonly BatchRow[];
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
  hasNewEntries?: boolean;
  onLoadNewEntries?: () => void;
  emptyTitle?: string;
  emptyDescription?: string;
  showBatchActions?: boolean;
  visibleBatchIds?: ReadonlySet<string>;
  onSortedChange?: (sorted: boolean) => void;
  bodyRef?: Ref<HTMLTableSectionElement>;
}) {
  const sorted = useSortableRows(batches, columns, { persist: true });
  const isSorted = sorted.sort !== null;
  const hasVisibleBatches =
    visibleBatchIds === undefined
      ? sorted.rows.length > 0
      : sorted.rows.some((batch) => visibleBatchIds.has(batch.id));
  const renderedRows =
    visibleBatchIds === undefined
      ? sorted.rows
      : [
          ...sorted.rows.filter((batch) => visibleBatchIds.has(batch.id)),
          ...sorted.rows.filter((batch) => !visibleBatchIds.has(batch.id)),
        ];

  useEffect(() => {
    onSortedChange?.(isSorted);
  }, [isSorted, onSortedChange]);

  if (!available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Batches unavailable</AlertTitle>
        <AlertDescription>{message ?? "Batches are currently unavailable."}</AlertDescription>
      </Alert>
    );
  }

  return (
    <Table>
      <TableHeader className="sticky top-0 z-10">
        <TableRow>
          <SortableTableHead
            label="Batch"
            columnKey="name"
            direction={directionFor("name", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6"
          />
          <SortableTableHead
            label="Total Jobs"
            columnKey="totalJobs"
            direction={directionFor("totalJobs", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[110px] px-6 text-right"
          />
          <SortableTableHead
            label="Pending"
            columnKey="pendingJobs"
            direction={directionFor("pendingJobs", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[100px] px-6 text-right"
          />
          <SortableTableHead
            label="Failed"
            columnKey="failedJobs"
            direction={directionFor("failedJobs", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[90px] px-6 text-right"
          />
          <SortableTableHead
            label="Progress"
            columnKey="progress"
            direction={directionFor("progress", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[150px] px-6"
          />
          <SortableTableHead
            label="Created At"
            columnKey="createdAt"
            direction={directionFor("createdAt", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[190px] px-6"
          />
          {showBatchActions ? (
            <TableHead className="w-14 pr-6 pl-3 text-right">Actions</TableHead>
          ) : null}
        </TableRow>
      </TableHeader>
      <TableBody role="presentation">
        {hasNewEntries && onLoadNewEntries ? (
          <NewEntriesTableRow columns={showBatchActions ? 7 : 6} onLoad={onLoadNewEntries} />
        ) : null}
        {!hasVisibleBatches ? (
          <TableEmpty
            columns={showBatchActions ? 7 : 6}
            title={emptyTitle ?? "No batches"}
            description={emptyDescription ?? "There aren't any batches."}
            icon={BatchesNavigationIcon}
          />
        ) : null}
      </TableBody>
      <TableBody ref={bodyRef}>
        {renderedRows.map((batch) => {
          const detailUrl = resolveHorizonRoute(batchShow(batch.id), horizonBaseUrl).url;

          return (
            <TableRow
              className="cursor-pointer"
              key={batch.id}
              hidden={visibleBatchIds !== undefined && !visibleBatchIds.has(batch.id)}
              onClick={(event) => {
                if (isInteractiveTarget(event.target)) {
                  return;
                }

                router.visit(detailUrl);
              }}
              onMouseEnter={() => router.prefetch(detailUrl)}
            >
              <TableCell className="max-w-sm px-6 whitespace-normal">
                <Link
                  className="truncate font-normal text-foreground"
                  href={detailUrl}
                  prefetch
                  title={batch.id}
                >
                  {batch.displayName}
                </Link>
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {numberFormatter.format(batch.totalJobs)}
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {numberFormatter.format(batch.pendingJobs)}
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {numberFormatter.format(batch.failedJobs)}
              </TableCell>
              <TableCell className="px-6 text-muted-foreground">
                <ProgressRing
                  value={batch.progress}
                  cancelled={batch.status === "cancelled"}
                  pendingJobs={batch.pendingJobs}
                  failedJobs={batch.failedJobs}
                />
              </TableCell>
              <TableCell className="px-6 text-muted-foreground">
                {dateFormatter.format(batch.createdAt * 1000)}
              </TableCell>
              {showBatchActions ? (
                <TableCell className="w-14 pr-6 pl-3 text-right">
                  <BatchPendingJobsActions
                    batchId={batch.id}
                    horizonBaseUrl={horizonBaseUrl}
                    canRetry={batch.failedJobs > 0}
                    canCancel={
                      batch.status !== "cancelled" &&
                      batch.status !== "finished" &&
                      Math.max(0, batch.pendingJobs - batch.failedJobs) > 0
                    }
                    ariaLabel={`Batch actions for ${batch.displayName}`}
                  />
                </TableCell>
              ) : null}
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
