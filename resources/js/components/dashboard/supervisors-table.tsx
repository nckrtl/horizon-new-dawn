import { Link, router } from "@inertiajs/react";
import { CpuIcon, TriangleAlertIcon } from "lucide-react";
import { useEffect, useRef, useState } from "react";

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
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { show as supervisorShow } from "@/generated/routes/horizon-new-dawn/supervisors";
import {
  sortRows,
  useSortableRows,
  type SortColumn,
  type SortState,
} from "@/hooks/use-sortable-rows";
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
const emptySupervisorRows: readonly SupervisorItem[] = [];

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

function HorizonInstance({
  autoRefreshEnabled,
  group,
  hasMoreThanTwoInstances,
  horizonBaseUrl,
  sort,
  onSort,
}: {
  autoRefreshEnabled: boolean;
  group: DashboardSupervisors["groups"][number];
  hasMoreThanTwoInstances: boolean;
  horizonBaseUrl: string;
  sort: SortState | null;
  onSort: (key: string) => void;
}) {
  const rows = sortRows(group.items, columns, sort);
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
                direction={directionFor(column.key, sort)}
                onSort={onSort}
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
          {rows.length === 0 ? (
            <TableEmpty
              columns={group.local ? 7 : 6}
              title="No supervisors"
              icon={DashboardNavigationIcon}
            />
          ) : null}
          {rows.map((item) => {
            const scaling = autoRefreshEnabled ? item.scaling : null;
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
                <TableCell tone="secondary" className="relative px-6 text-right tabular-nums">
                  <span className={scaling ? "mr-4" : undefined} data-process-count="">
                    {item.processes}
                  </span>
                  {scaling ? (
                    <SupervisorScalingIndicator
                      currentProcesses={item.processes}
                      scaling={scaling}
                    />
                  ) : null}
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

type SupervisorScalingState = "up" | "down" | "steady";
type ActiveSupervisorScalingState = Exclude<SupervisorScalingState, "steady">;

const scalingStates = {
  up: {
    label: "Scaling up",
    activeClassName: "text-status-success-icon",
  },
  down: {
    label: "Scaling down",
    activeClassName: "text-status-destructive-icon",
  },
  steady: {
    label: "Autoscaling idle",
    activeClassName: "",
  },
} satisfies Record<SupervisorScalingState, { label: string; activeClassName: string }>;

const scalingBlockPositions = [0, 1, 2, 3, 4] as const;
const scalingStepDuration = 720;

function SupervisorScalingIndicator({
  currentProcesses,
  scaling,
}: {
  currentProcesses: number;
  scaling: NonNullable<SupervisorItem["scaling"]>;
}) {
  const targetState: SupervisorScalingState =
    scaling.targetProcesses > currentProcesses
      ? "up"
      : scaling.targetProcesses < currentProcesses
        ? "down"
        : "steady";
  const [animationState, setAnimationState] = useState<ActiveSupervisorScalingState | null>(() =>
    targetState === "steady" ? null : targetState,
  );
  const [filledBlockCount, setFilledBlockCount] = useState(() =>
    targetState === "up" ? 1 : targetState === "down" ? 5 : 0,
  );
  const targetStateRef = useRef(targetState);
  targetStateRef.current = targetState;

  useEffect(() => {
    if (animationState === null && targetState !== "steady") {
      setAnimationState(targetState);
    }
  }, [animationState, targetState]);

  useEffect(() => {
    if (animationState === null) {
      setFilledBlockCount(0);

      return;
    }

    const sequence = animationState === "up" ? [1, 2, 3, 4, 5] : [5, 4, 3, 2, 1];
    let position = 0;

    setFilledBlockCount(sequence[0]);

    const timer = window.setInterval(() => {
      if (position === sequence.length - 1) {
        const nextState = targetStateRef.current;

        if (nextState !== animationState) {
          window.clearInterval(timer);
          setAnimationState(nextState === "steady" ? null : nextState);

          return;
        }
      }

      position = (position + 1) % sequence.length;
      setFilledBlockCount(sequence[position]);
    }, scalingStepDuration);

    return () => window.clearInterval(timer);
  }, [animationState]);

  const state = animationState ?? targetState;
  const isFinishing = animationState !== null && targetState !== animationState;
  const details = scalingStates[state];
  const strategy = scaling.strategy === "size" ? "Job-count" : "Time";
  const jobLabel = scaling.readyJobs === 1 ? "job" : "jobs";
  const processLabel = currentProcesses === 1 ? "process" : "processes";
  let label = `${details.label} at ${currentProcesses} ${processLabel}`;

  if (isFinishing) {
    label = `Finishing scale ${state} at ${currentProcesses} ${processLabel}`;
  } else if (state !== "steady") {
    const targetProcessLabel = scaling.targetProcesses === 1 ? "process" : "processes";
    label = `${details.label} from ${currentProcesses} ${processLabel} to ${scaling.targetProcesses} ${targetProcessLabel}`;
  }

  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <button
            type="button"
            className="absolute inset-y-1 right-6 z-10 w-2 cursor-help p-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card"
            data-scaling-state={state}
            data-scaling-filled={state === "steady" ? 0 : filledBlockCount}
            aria-label={`${label}. ${strategy}-based autoscaling with ${scaling.readyJobs} ready ${jobLabel}.`}
          >
            <div
              aria-hidden="true"
              data-scaling-track=""
              className="flex h-full w-full flex-col justify-center gap-px"
            >
              {scalingBlockPositions.map((position) => {
                const isFilled =
                  state !== "steady" && position >= scalingBlockPositions.length - filledBlockCount;

                return (
                  <span
                    key={position}
                    data-scaling-block=""
                    data-scaling-block-filled={isFilled}
                    className={cn(
                      "h-[3px] w-2 flex-none animate-none rounded-[1px] bg-muted-foreground/25 transition-none",
                      details.activeClassName,
                      isFilled && "bg-current",
                    )}
                  />
                );
              })}
            </div>
          </button>
        }
      />
      <TooltipContent side="left" sideOffset={8}>
        {label} · {strategy}-based · {scaling.readyJobs} ready {jobLabel}
      </TooltipContent>
    </Tooltip>
  );
}

export function SupervisorsTable({
  autoRefreshEnabled,
  supervisors,
  horizonBaseUrl,
}: {
  autoRefreshEnabled: boolean;
  supervisors: DashboardSupervisors;
  horizonBaseUrl: string;
}) {
  const sorting = useSortableRows(emptySupervisorRows, columns, {
    persist: true,
    prefix: "supervisors",
  });

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
              autoRefreshEnabled={autoRefreshEnabled}
              group={group}
              hasMoreThanTwoInstances={supervisors.groups.length > 2}
              horizonBaseUrl={horizonBaseUrl}
              sort={sorting.sort}
              onSort={sorting.toggle}
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
