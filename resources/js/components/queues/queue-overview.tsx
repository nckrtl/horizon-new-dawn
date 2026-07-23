import { Link } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";
import { lazy, Suspense } from "react";

import { ProgressRing } from "@/components/batches/progress-ring";
import {
  OverviewDetail,
  OverviewStatLink,
  retainedPeriodLabel,
} from "@/components/dashboard/dashboard-overview";
import { QueueActionsMenu, QueuePauseBadge } from "@/components/queues/queue-actions-menu";
import { QueueWaitThresholdMetric } from "@/components/queues/queue-wait-threshold";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Card, CardAction, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { show as queueShow } from "@/generated/routes/horizon-new-dawn/queues";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { MetricPreview } from "@/types/metrics";
import type { QueueActivityTab, QueueDetailView, QueueSummary } from "@/types/queues";

const numberFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 });
let metricChartPromise: Promise<{
  default: typeof import("@/components/metrics/metric-chart").MetricChart;
}> | null = null;

function loadMetricChart() {
  metricChartPromise ??= import("@/components/metrics/metric-chart").then(({ MetricChart }) => ({
    default: MetricChart,
  }));

  return metricChartPromise;
}

const MetricChart = lazy(loadMetricChart);

function retainedValue(value: number | null, complete: boolean) {
  if (value === null) {
    return "—";
  }

  return complete ? value : `${numberFormatter.format(value)}+`;
}

function activityUrl(queue: string, tab: QueueActivityTab, horizonBaseUrl: string) {
  const route = queueShow(encodeURIComponent(queue), { query: { tab } });

  return `${resolveHorizonRoute(route, horizonBaseUrl).url}#queue-activity`;
}

export function QueueOverview({
  view,
  tab,
  summary,
  preview,
  horizonBaseUrl,
  queuePausing = true,
}: {
  view: QueueDetailView;
  tab: QueueActivityTab;
  summary: QueueSummary;
  preview: MetricPreview | null;
  horizonBaseUrl: string;
  queuePausing?: boolean;
}) {
  if (!summary.available) {
    return (
      <Alert variant="destructive">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Queue unavailable</AlertTitle>
        <AlertDescription>
          {summary.message ?? "This queue is no longer supervised by Horizon."}
        </AlertDescription>
      </Alert>
    );
  }

  const viewUrl = (nextView: QueueDetailView) => {
    const route = queueShow(encodeURIComponent(summary.name), {
      query: nextView === "metrics" ? { view: nextView, tab } : { tab },
    });

    return resolveHorizonRoute(route, horizonBaseUrl).url;
  };

  return (
    <Card>
      <CardHeader className="h-[54px] min-h-[54px] border-b-0 py-2.5">
        <CardTitle className="flex min-w-0 items-center gap-2" title={summary.name}>
          <span className="truncate">{summary.name}</span>
          {summary.pauseTargets.map((target) => (
            <QueuePauseBadge
              key={target.connection}
              paused={target.paused}
              pausedUntil={target.pausedUntil}
              connection={summary.pauseTargets.length > 1 ? target.connection : undefined}
            />
          ))}
        </CardTitle>
        <CardAction>
          <QueueActionsMenu
            queue={summary.name}
            targets={summary.pauseTargets}
            failedJobs={summary.failedJobs}
            horizonBaseUrl={horizonBaseUrl}
            queuePausing={queuePausing}
          />
        </CardAction>
      </CardHeader>
      <CardContent className="p-0">
        <Tabs value={view} className="gap-0">
          <TabsList
            variant="line"
            className="w-full justify-start gap-2 overflow-x-auto rounded-none border-b border-separator px-3 py-0"
            aria-label="Queue view"
          >
            <TabsTrigger
              className="h-auto flex-none rounded-none px-3 pt-0 pb-3 text-[13.5px]"
              value="overview"
              nativeButton={false}
              render={<Link href={viewUrl("overview")} prefetch preserveState />}
            >
              Overview
            </TabsTrigger>
            <TabsTrigger
              className="h-auto flex-none rounded-none px-3 pt-0 pb-3 text-[13.5px]"
              value="metrics"
              nativeButton={false}
              render={
                <Link
                  href={viewUrl("metrics")}
                  prefetch
                  preserveState
                  onFocus={() => void loadMetricChart()}
                  onMouseEnter={() => void loadMetricChart()}
                />
              }
            >
              Metrics
            </TabsTrigger>
          </TabsList>
        </Tabs>
        {summary.message ? (
          <Alert className="m-4">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Some queue data is unavailable</AlertTitle>
            <AlertDescription>{summary.message}</AlertDescription>
          </Alert>
        ) : null}
        {view === "metrics" ? (
          <QueueMetrics name={summary.name} preview={preview} />
        ) : (
          <QueueStatistics summary={summary} horizonBaseUrl={horizonBaseUrl} />
        )}
      </CardContent>
    </Card>
  );
}

function QueueStatistics({
  summary,
  horizonBaseUrl,
}: {
  summary: QueueSummary;
  horizonBaseUrl: string;
}) {
  return (
    <>
      <div className="grid gap-px bg-separator sm:grid-cols-2 md:grid-cols-4">
        <OverviewStatLink
          href={activityUrl(summary.name, "pending", horizonBaseUrl)}
          title="Retained Pending Jobs"
          value={retainedValue(summary.pendingJobs, summary.pendingComplete)}
        >
          <OverviewDetail label="Live reserved" value={summary.pendingReserved} />
          <OverviewDetail label="Live ready" value={summary.pendingReadyNow} />
          <OverviewDetail label="Live delayed" value={summary.pendingDelayed} />
        </OverviewStatLink>
        <OverviewStatLink
          href={activityUrl(summary.name, "failed", horizonBaseUrl)}
          title="Failed Jobs"
          value={retainedValue(summary.failedJobs, summary.failedComplete)}
        >
          <OverviewDetail
            label="Average/minute"
            value={retainedValue(summary.failedJobsPerMinute, summary.failedJobsPerMinuteComplete)}
          />
          <OverviewDetail
            label={retainedPeriodLabel(summary.failedRetentionMinutes, 60)}
            value={retainedValue(summary.failedJobsPastHour, summary.failedJobsPastHourComplete)}
          />
          <OverviewDetail
            label={retainedPeriodLabel(summary.failedRetentionMinutes, 1440)}
            value={retainedValue(summary.failedJobsPastDay, summary.failedJobsPastDayComplete)}
          />
        </OverviewStatLink>
        <OverviewStatLink
          href={activityUrl(summary.name, "completed", horizonBaseUrl)}
          title="Completed Jobs"
          value={retainedValue(summary.completedJobs, summary.completedComplete)}
        >
          <OverviewDetail
            label="Average/minute"
            value={retainedValue(
              summary.completedJobsPerMinute,
              summary.completedJobsPerMinuteComplete,
            )}
          />
          <OverviewDetail
            label={retainedPeriodLabel(summary.completedRetentionMinutes, 60)}
            value={retainedValue(
              summary.completedJobsPastHour,
              summary.completedJobsPastHourComplete,
            )}
          />
          <OverviewDetail
            label={retainedPeriodLabel(summary.completedRetentionMinutes, 1440)}
            value={retainedValue(
              summary.completedJobsPastDay,
              summary.completedJobsPastDayComplete,
            )}
          />
        </OverviewStatLink>
        <OverviewStatLink
          href={activityUrl(summary.name, "batches", horizonBaseUrl)}
          title="Batches in progress"
          value={retainedValue(summary.activeBatches, summary.batchesComplete)}
          unit="in progress"
        >
          {summary.batchPreviews.slice(0, 3).map((batch) => (
            <div
              className="flex items-center justify-between gap-2 border-t border-dashed border-separator py-[7px] text-[13px]"
              key={batch.id}
            >
              <span className="truncate text-muted-foreground">{batch.name}</span>
              <ProgressRing
                className="shrink-0 gap-1.5 [&_span]:text-[13px]"
                value={batch.progress}
              />
            </div>
          ))}
        </OverviewStatLink>
      </div>
      <div className="grid gap-px border-t border-separator bg-separator sm:grid-cols-2 md:grid-cols-4">
        <QueueMetric label="Total processes" value={summary.processes ?? "—"} />
        {summary.waitThreshold ? (
          <QueueWaitThresholdMetric waitThreshold={summary.waitThreshold} />
        ) : (
          <QueueMetric label="Wait threshold" value="—" />
        )}
        <QueueMetric
          label="Average job runtime"
          value={
            summary.averageRuntime === null
              ? "—"
              : `${numberFormatter.format(summary.averageRuntime)}s`
          }
          detail="Since last snapshot"
        />
        <QueueMetric
          label="Throughput"
          value={summary.throughput ?? "—"}
          detail="Since last snapshot"
        />
      </div>
    </>
  );
}

function QueueMetrics({ name, preview }: { name: string; preview: MetricPreview | null }) {
  const snapshots = preview?.data ?? [];

  return (
    <>
      {preview !== null && !preview.available ? (
        <Alert variant="destructive" className="m-4">
          <TriangleAlertIcon aria-hidden="true" />
          <AlertTitle>Metrics unavailable</AlertTitle>
          <AlertDescription>
            {preview.message ?? "Metrics for this queue are currently unavailable."}
          </AlertDescription>
        </Alert>
      ) : null}
      <div className="grid gap-px bg-separator md:grid-cols-2">
        <QueueMetricChart title={`Throughput — ${name}`}>
          <Suspense fallback={<MetricChartSkeleton />}>
            <MetricChart kind="throughput" snapshots={snapshots} />
          </Suspense>
        </QueueMetricChart>
        <QueueMetricChart title={`Runtime — ${name}`}>
          <Suspense fallback={<MetricChartSkeleton />}>
            <MetricChart kind="runtime" snapshots={snapshots} />
          </Suspense>
        </QueueMetricChart>
      </div>
    </>
  );
}

function MetricChartSkeleton() {
  return (
    <div className="flex flex-col gap-3 px-6" aria-label="Loading queue metrics">
      <Skeleton className="h-[205px] w-full" />
      <Skeleton className="mx-auto h-3 w-28" />
    </div>
  );
}

function QueueMetricChart({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="bg-card">
      <h2 className="truncate px-6 py-4 text-sm font-medium" title={title}>
        {title}
      </h2>
      <div className="px-0 pt-3 pb-2 [&_[data-slot=chart]]:h-[245px]">{children}</div>
    </section>
  );
}

function QueueMetric({
  label,
  value,
  detail,
}: {
  label: string;
  value: number | string;
  detail?: string;
}) {
  return (
    <div className="bg-card px-6 py-4">
      <p className="text-[13px] font-medium text-muted-foreground">{label}</p>
      <p className="mt-3 text-[1.375rem] font-semibold tracking-tight tabular-nums">{value}</p>
      {detail ? <p className="mt-0.5 text-[13px] text-muted-foreground">{detail}</p> : null}
    </div>
  );
}
