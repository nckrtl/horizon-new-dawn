import { Link, router } from "@inertiajs/react";
import { CpuIcon, TriangleAlertIcon } from "lucide-react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import {
  HorizonInstanceActions,
  HorizonInstancesActions,
  SupervisorActions,
} from "@/components/dashboard/horizon-instances-actions";
import { TableEmpty } from "@/components/data-table/table-empty";
import { StatusBadge } from "@/components/dashboard/status-badge";
import { DashboardNavigationIcon } from "@/components/navigation-icons";
import { ListPageHeader } from "@/components/shell/list-page-header";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Card, CardContent } from "@/components/ui/card";
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
import { show as supervisorShow } from "@/generated/routes/horizon-new-dawn/supervisors";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import { cn } from "@/lib/utils";
import type {
  DashboardSupervisors,
  HorizonStatus as HorizonStatusValue,
  SupervisorItem,
} from "@/types/dashboard";

const columns: SortColumn<SupervisorItem>[] = [
  { key: "name", value: (item) => item.name },
  { key: "status", value: (item) => item.status },
  { key: "connection", value: (item) => item.connection },
  { key: "queues", value: (item) => item.queues.join(", ") },
  { key: "processes", value: (item) => item.processes },
  { key: "balancing", value: (item) => item.balancing },
];

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

function HorizonInstance({
  group,
  hasMoreThanTwoInstances,
  horizonBaseUrl,
}: {
  group: DashboardSupervisors["groups"][number];
  hasMoreThanTwoInstances: boolean;
  horizonBaseUrl: string;
}) {
  const sorted = useSortableRows(group.items, columns, {
    persist: true,
    prefix: "supervisors",
  });
  const status = normalizeStatus(group.status);

  return (
    <section aria-labelledby={`horizon-instance-${group.name}`}>
      <div
        className={cn(
          "flex min-h-[54px] items-center justify-between gap-4 border-b border-separator px-6 pb-3",
          hasMoreThanTwoInstances ? "pt-6" : "pt-4",
        )}
      >
        <div className="min-w-0">
          <h3
            className="min-w-0 truncate font-heading text-[15px] leading-snug font-semibold tracking-[-0.01em]"
            id={`horizon-instance-${group.name}`}
            title={group.name}
          >
            {group.name}
          </h3>
          <p className="mt-1 flex min-h-5 flex-wrap items-center gap-1.5 text-[12.5px] text-muted-foreground">
            Environment: {group.environment ?? "—"}
            {"\u00A0\u00A0·\u00A0\u00A0"}
            PID {group.pid ?? "—"}
            {group.local ? " (This machine)" : null}
          </p>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <StatusBadge status={status} />
          {group.local ? (
            <HorizonInstanceActions
              horizonBaseUrl={horizonBaseUrl}
              instanceName={group.name}
              status={status}
            />
          ) : null}
        </div>
      </div>
      <Table>
        <TableHeader>
          <TableRow>
            {columns.map((column) => (
              <SortableTableHead
                key={column.key}
                label={
                  column.key === "name"
                    ? "Supervisor"
                    : `${column.key[0].toUpperCase()}${column.key.slice(1)}`
                }
                columnKey={column.key}
                direction={directionFor(column.key, sorted.sort)}
                onSort={sorted.toggle}
                className={
                  column.key === "processes" || column.key === "balancing"
                    ? "px-6 text-right"
                    : "px-6"
                }
              />
            ))}
            {group.local ? <TableHead className="px-6 text-right">Actions</TableHead> : null}
          </TableRow>
        </TableHeader>
        <TableBody>
          {sorted.rows.length === 0 ? (
            <TableEmpty
              columns={group.local ? 7 : 6}
              title="No supervisors"
              icon={DashboardNavigationIcon}
            />
          ) : null}
          {sorted.rows.map((item) => {
            const detailUrl = resolveHorizonRoute(
              supervisorShow(encodeURIComponent(item.id)),
              horizonBaseUrl,
            ).url;

            return (
              <TableRow
                key={item.id}
                onClick={(event) => {
                  if (isInteractiveTarget(event.target)) {
                    return;
                  }

                  router.visit(detailUrl);
                }}
                onMouseEnter={() => router.prefetch(detailUrl)}
              >
                <TableCell className="px-6">
                  <Link className="font-normal text-foreground" href={detailUrl} prefetch>
                    {item.name}
                  </Link>
                </TableCell>
                <TableCell className="px-6">
                  <StatusBadge status={normalizeStatus(item.status)} />
                </TableCell>
                <TableCell tone="secondary" className="px-6">
                  {item.connection}
                </TableCell>
                <TableCell tone="secondary" className="px-6">
                  {item.queues.join(", ")}
                </TableCell>
                <TableCell tone="secondary" className="px-6 text-right tabular-nums">
                  {item.processes}
                </TableCell>
                <TableCell tone="secondary" className="px-6 text-right">
                  {item.balancing}
                </TableCell>
                {group.local ? (
                  <TableCell className="px-6 text-right">
                    <SupervisorActions
                      horizonBaseUrl={horizonBaseUrl}
                      supervisor={item.id}
                      status={normalizeStatus(item.status)}
                    />
                  </TableCell>
                ) : null}
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </section>
  );
}

export function SupervisorsTable({
  supervisors,
  horizonBaseUrl,
}: {
  supervisors: DashboardSupervisors;
  horizonBaseUrl: string;
}) {
  if (!supervisors.available) {
    return (
      <Alert variant="destructive">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Running instances unavailable</AlertTitle>
        <AlertDescription>
          {supervisors.message ?? "Horizon supervisors are currently unavailable."}
        </AlertDescription>
      </Alert>
    );
  }

  if (supervisors.groups.length === 0) {
    return (
      <Card>
        <ListPageHeader
          title={<h2>Instances</h2>}
          actions={
            <HorizonInstancesActions horizonBaseUrl={horizonBaseUrl} hasLocalInstance={false} />
          }
        />
        <CardContent className="p-0">
          <Empty className="min-h-40">
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <CpuIcon aria-hidden="true" />
              </EmptyMedia>
              <EmptyTitle>No Horizon instances</EmptyTitle>
              <EmptyDescription>
                Run <code className="font-sans">php artisan horizon</code> to start an instance and
                start processing queues.
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        </CardContent>
      </Card>
    );
  }

  const supervisorCount = supervisors.groups.reduce(
    (total, group) => total + group.items.length,
    0,
  );
  const pausedSupervisorCount = supervisors.groups.reduce(
    (total, group) =>
      total + group.items.filter((supervisor) => supervisor.status === "paused").length,
    0,
  );
  const localGroups = supervisors.groups.filter((group) => group.local);

  return (
    <Card>
      <ListPageHeader
        title={<h2>Instances</h2>}
        actions={
          <HorizonInstancesActions
            horizonBaseUrl={horizonBaseUrl}
            hasLocalInstance={localGroups.length > 0}
          />
        }
      />
      <CardContent className="p-0">
        <div className="grid gap-px border-b border-separator bg-separator sm:grid-cols-3">
          <InstanceStat label="Total Instances" value={supervisors.groups.length} />
          <InstanceStat label="Total Supervisors" value={supervisorCount} />
          <InstanceStat label="Paused Supervisors" value={pausedSupervisorCount} />
        </div>
        <div className="divide-y divide-separator">
          {supervisors.groups.map((group) => (
            <HorizonInstance
              group={group}
              hasMoreThanTwoInstances={supervisors.groups.length > 2}
              horizonBaseUrl={horizonBaseUrl}
              key={group.name}
            />
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

function InstanceStat({ label, value }: { label: string; value: number }) {
  return (
    <div className="bg-card px-6 py-4">
      <p className="text-[13px] font-medium text-muted-foreground">{label}</p>
      <p className="mt-3 text-[1.375rem] font-semibold tracking-tight tabular-nums">{value}</p>
    </div>
  );
}

function normalizeStatus(status: string): HorizonStatusValue {
  return status === "running" || status === "paused" || status === "unavailable"
    ? status
    : "inactive";
}
