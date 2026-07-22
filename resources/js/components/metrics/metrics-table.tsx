import { Link, router } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";

import { SortableTableHead } from "@/components/data-table/sortable-table-head";
import { TableEmpty } from "@/components/data-table/table-empty";
import { MetricsNavigationIcon } from "@/components/navigation-icons";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Table, TableBody, TableCell, TableHeader, TableRow } from "@/components/ui/table";
import { show as metricShow } from "@/generated/routes/horizon-new-dawn/metrics";
import { useSortableRows, type SortColumn } from "@/hooks/use-sortable-rows";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import type { MetricRow, MetricType } from "@/types/metrics";

const columns: SortColumn<MetricRow>[] = [{ key: "name", value: (metric) => metric.name }];

function directionFor(key: string, sort: { key: string; direction: "asc" | "desc" } | null) {
  return sort?.key === key ? sort.direction : undefined;
}

export function MetricsTable({
  type,
  metrics,
  horizonBaseUrl,
  available = true,
  message = null,
  emptyTitle,
  emptyDescription,
}: {
  type: MetricType;
  metrics: readonly MetricRow[];
  horizonBaseUrl: string;
  available?: boolean;
  message?: string | null;
  emptyTitle?: string;
  emptyDescription?: string;
}) {
  const sorted = useSortableRows(metrics, columns, { persist: true });
  const singular = type === "jobs" ? "job" : "queue";

  if (!available) {
    return (
      <Alert variant="destructive" className="m-4">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Metrics unavailable</AlertTitle>
        <AlertDescription>
          {message ?? `${singular[0].toUpperCase()}${singular.slice(1)} metrics are unavailable.`}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <SortableTableHead
            label={type === "jobs" ? "Job" : "Queue"}
            columnKey="name"
            direction={directionFor("name", sorted.sort)}
            onSort={sorted.toggle}
            className="px-6"
          />
        </TableRow>
      </TableHeader>
      <TableBody>
        {sorted.rows.length === 0 ? (
          <TableEmpty
            columns={1}
            title={emptyTitle ?? `No ${singular} metrics yet`}
            description={
              emptyDescription ??
              `Once Horizon records its first ${singular} snapshot, throughput and runtime history will settle in here.`
            }
            icon={MetricsNavigationIcon}
          />
        ) : null}
        {sorted.rows.map((metric) => {
          const detailUrl = resolveHorizonRoute(
            metricShow({ type, slug: encodeURIComponent(metric.name) }),
            horizonBaseUrl,
          ).url;

          return (
            <TableRow
              className="cursor-pointer"
              key={metric.name}
              onClick={(event) => {
                if (isInteractiveTarget(event.target)) {
                  return;
                }

                router.visit(detailUrl);
              }}
              onMouseEnter={() => router.prefetch(detailUrl)}
            >
              <TableCell className="px-6">
                <Link className="text-foreground" href={detailUrl} prefetch>
                  {metric.name}
                </Link>
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
