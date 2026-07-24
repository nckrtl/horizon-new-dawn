import { Link, router } from "@inertiajs/react";
import { ChevronRightIcon, TriangleAlertIcon } from "lucide-react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { Duration } from "@/components/duration";
import { DashboardNavigationIcon } from "@/components/navigation-icons";
import { QueueActionsMenu, QueuePauseBadge } from "@/components/queues/queue-actions-menu";
import {
  QueueWaitThresholdCell,
  queueWaitThresholdSortRank,
} from "@/components/queues/queue-wait-threshold";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
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
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import { cn } from "@/lib/utils";
import type { DashboardWorkload, WorkloadItem } from "@/types/dashboard";

const numberFormatter = new Intl.NumberFormat();
const columns: SortColumn<WorkloadItem>[] = [
  { key: "name", value: (item) => item.name },
  {
    key: "waitThreshold",
    value: (item) => queueWaitThresholdSortRank[item.waitThreshold?.status ?? "disabled"],
  },
  { key: "length", value: (item) => item.length },
  { key: "processes", value: (item) => item.processes },
  { key: "wait", value: (item) => item.wait },
];

function queueName(name: string) {
  return name
    .split(",")
    .map((queue) => queue.trim())
    .filter(Boolean)
    .join(", ");
}

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

export function WorkloadTable({
  workload,
  horizonBaseUrl,
  queuePausing = true,
}: {
  workload: DashboardWorkload;
  horizonBaseUrl: string;
  queuePausing?: boolean;
}) {
  const sorted = useSortableRows(workload.items, columns, { persist: true, prefix: "workload" });

  if (!workload.available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Workload unavailable</AlertTitle>
        <AlertDescription>
          {workload.message ?? "Horizon workload is currently unavailable."}
        </AlertDescription>
      </Alert>
    );
  }

  if (workload.items.length === 0) {
    return (
      <Empty className="min-h-48">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <DashboardNavigationIcon aria-hidden="true" />
          </EmptyMedia>
          <EmptyTitle>All queues are clear</EmptyTitle>
          <EmptyDescription>Horizon has no queued workload right now.</EmptyDescription>
        </EmptyHeader>
      </Empty>
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
            className="w-[160px] px-6"
          />
          <SortableTableHead
            label="Ready Jobs"
            columnKey="length"
            direction={directionFor("length", sorted.sort)}
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
          <TableHead className="w-12 pr-6 pl-3 text-right">
            <span className="sr-only">Actions</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {sorted.rows.map((item) => (
          <WorkloadRows
            item={item}
            horizonBaseUrl={horizonBaseUrl}
            queuePausing={queuePausing}
            key={item.name}
          />
        ))}
      </TableBody>
    </Table>
  );
}

function WorkloadRows({
  item,
  horizonBaseUrl,
  queuePausing,
}: {
  item: WorkloadItem;
  horizonBaseUrl: string;
  queuePausing: boolean;
}) {
  const grouped = item.splitQueues !== null && item.splitQueues.length > 0;
  const detailUrl = grouped
    ? null
    : resolveHorizonRoute(queueShow(encodeURIComponent(item.name)), horizonBaseUrl).url;

  return (
    <>
      <TableRow
        onClick={
          detailUrl
            ? (event) => {
                if (isInteractiveTarget(event.target)) {
                  return;
                }

                router.visit(detailUrl);
              }
            : undefined
        }
        onMouseEnter={detailUrl ? () => router.prefetch(detailUrl) : undefined}
      >
        <TableCell className={cn("px-6", grouped && "font-semibold")}>
          {detailUrl ? (
            <Link
              className="inline-flex items-center gap-2 text-foreground"
              href={detailUrl}
              prefetch
            >
              {queueName(item.name)}
              <QueuePauseBadge paused={item.paused} pausedUntil={item.pausedUntil} />
            </Link>
          ) : (
            <span className="inline-flex items-center gap-2">{queueName(item.name)}</span>
          )}
        </TableCell>
        <TableCell tone="secondary" className="px-6">
          <QueueWaitThresholdCell waitThreshold={item.waitThreshold} />
        </TableCell>
        <TableCell
          tone="secondary"
          className={cn("px-6 text-right tabular-nums", grouped && "font-semibold")}
        >
          {numberFormatter.format(item.length)}
        </TableCell>
        <TableCell
          tone="secondary"
          className={cn("px-6 text-right tabular-nums", grouped && "font-semibold")}
        >
          {numberFormatter.format(item.processes)}
        </TableCell>
        <TableCell
          tone="secondary"
          className={cn("px-6 text-right tabular-nums", grouped && "font-semibold")}
        >
          <Duration seconds={item.wait} />
        </TableCell>
        <TableCell tone="secondary" className="pr-6 pl-3 text-right">
          {!grouped ? (
            <QueueActionsMenu
              connection={item.connection}
              queue={item.name}
              paused={item.paused}
              pausedUntil={item.pausedUntil}
              pendingJobs={item.length}
              horizonBaseUrl={horizonBaseUrl}
              queuePausing={queuePausing}
            />
          ) : null}
        </TableCell>
      </TableRow>
      {item.splitQueues?.map((queue) => {
        const splitDetailUrl = resolveHorizonRoute(
          queueShow(encodeURIComponent(queue.name)),
          horizonBaseUrl,
        ).url;

        return (
          <TableRow
            key={`${item.name}:${queue.name}`}
            onClick={(event) => {
              if (isInteractiveTarget(event.target)) {
                return;
              }

              router.visit(splitDetailUrl);
            }}
            onMouseEnter={() => router.prefetch(splitDetailUrl)}
          >
            <TableCell tone="secondary" className="px-6 pl-11">
              <Link
                className="inline-flex items-center gap-2 text-muted-foreground"
                href={splitDetailUrl}
                prefetch
              >
                <ChevronRightIcon aria-hidden="true" />
                {queue.name}
                <QueuePauseBadge paused={queue.paused} pausedUntil={queue.pausedUntil} />
              </Link>
            </TableCell>
            <TableCell tone="secondary" className="px-6">
              <QueueWaitThresholdCell waitThreshold={queue.waitThreshold} />
            </TableCell>
            <TableCell tone="secondary" className="px-6 text-right tabular-nums">
              {numberFormatter.format(queue.length)}
            </TableCell>
            <TableCell tone="secondary" className="px-6 text-right">
              —
            </TableCell>
            <TableCell tone="secondary" className="px-6 text-right tabular-nums">
              <Duration seconds={queue.wait} />
            </TableCell>
            <TableCell tone="secondary" className="pr-6 pl-3 text-right">
              <QueueActionsMenu
                connection={item.connection}
                queue={queue.name}
                paused={queue.paused}
                pausedUntil={queue.pausedUntil}
                pendingJobs={queue.length}
                horizonBaseUrl={horizonBaseUrl}
                queuePausing={queuePausing}
              />
            </TableCell>
          </TableRow>
        );
      })}
    </>
  );
}
