import { Link, router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { TableEmpty } from "@/components/data-table/table-empty";
import { QueueNavigationIcon } from "@/components/navigation-icons";
import { QueueActionsMenu, QueuePauseBadge } from "@/components/queues/queue-actions-menu";
import {
  QueueWaitThresholdCell,
  queueWaitThresholdSortRank,
} from "@/components/queues/queue-wait-threshold";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { show as queueShow } from "@/generated/routes/horizon-new-dawn/queues";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { formatDuration } from "@/lib/format-duration";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import type { QueueList, QueueRow } from "@/types/queues";

const numberFormatter = new Intl.NumberFormat();
const columns: SortColumn<QueueRow>[] = [
  { key: "name", value: (queue) => queue.name },
  {
    key: "waitThreshold",
    value: (queue) => queueWaitThresholdSortRank[queue.waitThreshold.status],
  },
  { key: "ready", value: (queue) => queue.ready },
  { key: "delayed", value: (queue) => queue.delayed },
  { key: "processes", value: (queue) => queue.processes },
  { key: "wait", value: (queue) => queue.wait },
];

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

export function QueueTable({
  queues,
  horizonBaseUrl,
  queuePausing = true,
  emptyTitle,
  emptyDescription,
}: {
  queues: QueueList;
  horizonBaseUrl: string;
  queuePausing?: boolean;
  emptyTitle?: string;
  emptyDescription?: string;
}) {
  const sorted = useSortableRows(queues.queues, columns, { persist: true, prefix: "queues" });

  if (!queues.available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Queues unavailable</AlertTitle>
        <AlertDescription>
          {queues.message ?? "Horizon queues are currently unavailable."}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <Table>
      <TableHeader className="sticky top-0 z-10">
        <TableRow>
          <SortableTableHead
            label="Queue"
            columnKey="name"
            direction={directionFor("name", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[32%] px-6"
          />
          <SortableTableHead
            label="Wait threshold"
            columnKey="waitThreshold"
            direction={directionFor("waitThreshold", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[190px] px-6"
          />
          <SortableTableHead
            label="Ready Jobs"
            columnKey="ready"
            direction={directionFor("ready", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6 text-right"
          />
          <SortableTableHead
            label="Delayed Jobs"
            columnKey="delayed"
            direction={directionFor("delayed", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6 text-right"
          />
          <SortableTableHead
            label="Processes"
            columnKey="processes"
            direction={directionFor("processes", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[130px] px-6 text-right"
          />
          <SortableTableHead
            label="Wait"
            columnKey="wait"
            direction={directionFor("wait", sorted.sort)}
            onSort={sorted.toggle}
            className="w-[150px] px-6 text-right"
          />
          <TableHead className="w-[80px] pr-3 pl-6 text-right">Actions</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {sorted.rows.length === 0 ? (
          <TableEmpty
            columns={7}
            title={emptyTitle ?? "All queues are clear"}
            description={emptyDescription ?? "Horizon has no supervised queues right now."}
            icon={QueueNavigationIcon}
          />
        ) : null}
        {sorted.rows.map((queue) => {
          const detailUrl = resolveHorizonRoute(
            queueShow(encodeURIComponent(queue.name)),
            horizonBaseUrl,
          ).url;

          return (
            <TableRow
              className="cursor-pointer"
              key={queue.name}
              onClick={(event) => {
                if (isInteractiveTarget(event.target)) {
                  return;
                }

                router.visit(detailUrl);
              }}
              onMouseEnter={() => router.prefetch(detailUrl)}
            >
              <TableCell className="px-6">
                <Link
                  className="inline-flex items-center gap-2 font-normal text-foreground"
                  href={detailUrl}
                  prefetch
                >
                  <span>{queue.name}</span>
                  {queue.pauseTargets.map((target) => (
                    <QueuePauseBadge
                      key={target.connection}
                      paused={target.paused}
                      pausedUntil={target.pausedUntil}
                      connection={queue.pauseTargets.length > 1 ? target.connection : undefined}
                    />
                  ))}
                </Link>
                {queue.connections.length > 1 ? (
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {queue.connections.join(", ")}
                  </p>
                ) : null}
              </TableCell>
              <TableCell className="px-6">
                <QueueWaitThresholdCell waitThreshold={queue.waitThreshold} />
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {numberFormatter.format(queue.ready)}
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {numberFormatter.format(queue.delayed)}
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {numberFormatter.format(queue.processes)}
              </TableCell>
              <TableCell className="px-6 text-right tabular-nums text-muted-foreground">
                {formatDuration(queue.wait)}
              </TableCell>
              <TableCell className="pr-3 pl-6 text-right">
                <QueueActionsMenu
                  queue={queue.name}
                  targets={queue.pauseTargets}
                  horizonBaseUrl={horizonBaseUrl}
                  queuePausing={queuePausing}
                />
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
