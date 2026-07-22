import { Link } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";

import { ProgressRing } from "@/components/batches/progress-ring";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type { DashboardSummary } from "@/types/dashboard";

const numberFormatter = new Intl.NumberFormat(undefined, {
  maximumFractionDigits: 2,
});

function formatRetention(minutes: number) {
  if (minutes <= 0) {
    return "no retained history";
  }

  if (minutes % 1440 === 0) {
    const days = minutes / 1440;

    return `${days} ${days === 1 ? "day" : "days"}`;
  }

  if (minutes % 60 === 0) {
    const hours = minutes / 60;

    return `${hours} ${hours === 1 ? "hour" : "hours"}`;
  }

  return `${minutes} ${minutes === 1 ? "minute" : "minutes"}`;
}

export function retainedPeriodLabel(retentionMinutes: number, periodMinutes: 60 | 1440) {
  if (retentionMinutes >= periodMinutes) {
    return periodMinutes === 60 ? "Past hour" : "Past 24 hours";
  }

  return retentionMinutes <= 0
    ? "Retained history"
    : `Retained ${formatRetention(retentionMinutes)}`;
}

export function OverviewDetail({
  label,
  value,
  tooltip,
}: {
  label: string;
  value: number | string | null;
  tooltip?: string;
}) {
  const detail = (
    <div className="flex justify-between gap-3 border-t border-dashed border-separator py-[7px] text-[13px]">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-normal text-muted-foreground">
        {value === null ? "—" : typeof value === "number" ? numberFormatter.format(value) : value}
      </span>
    </div>
  );

  if (!tooltip) {
    return detail;
  }

  return (
    <Tooltip>
      <TooltipTrigger render={<div />}>{detail}</TooltipTrigger>
      <TooltipContent side="top">{tooltip}</TooltipContent>
    </Tooltip>
  );
}

export function OverviewStatLink({
  href,
  title,
  value,
  unit,
  children,
}: {
  href: string;
  title: string;
  value: number | string;
  unit?: string;
  children: React.ReactNode;
}) {
  return (
    <Link
      href={href}
      prefetch
      className="block min-w-0 bg-card px-6 pt-4 pb-2.5 outline-none transition-colors hover:bg-table-row-hover focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
    >
      <p className="text-[13px] font-medium text-muted-foreground">{title}</p>
      <p className="mt-3 text-[1.375rem] font-semibold tracking-tight tabular-nums">
        {typeof value === "number" ? numberFormatter.format(value) : value}
        {unit ? (
          <span className="ml-2 text-[13px] font-medium text-muted-foreground">{unit}</span>
        ) : null}
      </p>
      <div className="mt-3.5">{children}</div>
    </Link>
  );
}

export function DashboardOverview({
  summary,
  links,
}: {
  summary: DashboardSummary;
  links: {
    pending: string;
    failed: string;
    completed: string;
    batches: string;
    batch: (id: string) => string;
  };
}) {
  if (!summary.available) {
    return (
      <Alert variant="destructive">
        <TriangleAlertIcon aria-hidden="true" />
        <AlertTitle>Horizon connection interrupted</AlertTitle>
        <AlertDescription>
          {summary.message ?? "Horizon data is currently unavailable."}
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Overview</CardTitle>
      </CardHeader>
      <CardContent className="grid gap-px bg-separator p-0 sm:grid-cols-2 md:grid-cols-4">
        <OverviewStatLink href={links.pending} title="Pending Jobs" value={summary.pendingJobs}>
          <OverviewDetail
            label="Reserved"
            value={summary.pendingReserved}
            tooltip="Jobs currently being worked on."
          />
          <OverviewDetail
            label="Ready"
            value={summary.pendingReadyNow}
            tooltip="Jobs waiting for an available worker."
          />
          <OverviewDetail
            label="Delayed"
            value={summary.pendingDelayed}
            tooltip="Jobs scheduled to run later."
          />
        </OverviewStatLink>
        <OverviewStatLink href={links.failed} title="Failed Jobs" value={summary.failedJobs}>
          <OverviewDetail label="Average/minute" value={summary.failedJobsPerMinute} />
          <OverviewDetail
            label={retainedPeriodLabel(summary.failedRetentionMinutes, 60)}
            value={summary.failedJobsPastHour}
          />
          <OverviewDetail
            label={retainedPeriodLabel(summary.failedRetentionMinutes, 1440)}
            value={summary.failedJobsPastDay}
          />
        </OverviewStatLink>
        <OverviewStatLink
          href={links.completed}
          title="Completed Jobs"
          value={summary.completedJobs}
        >
          <OverviewDetail label="Average/minute" value={summary.completedJobsPerMinute} />
          <OverviewDetail
            label={retainedPeriodLabel(summary.completedRetentionMinutes, 60)}
            value={summary.completedJobsPastHour}
          />
          <OverviewDetail
            label={retainedPeriodLabel(summary.completedRetentionMinutes, 1440)}
            value={summary.completedJobsPastDay}
          />
        </OverviewStatLink>
        <div className="min-w-0 bg-card px-6 pt-4 pb-2.5 outline-none transition-colors hover:bg-table-row-hover">
          <Link
            href={links.batches}
            prefetch
            className="block outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <p className="text-[13px] font-medium text-muted-foreground">Batches in progress</p>
            <p className="mt-3 text-[1.375rem] font-semibold tracking-tight tabular-nums">
              {numberFormatter.format(summary.activeBatches)}
              <span className="ml-2 text-[13px] font-medium text-muted-foreground">
                in progress
              </span>
            </p>
          </Link>
          <div className="mt-3.5">
            {summary.batchPreviews.slice(0, 3).map((batch) => (
              <Link
                className="flex items-center justify-between gap-2 border-t border-dashed border-separator py-[7px] text-[13px] outline-none focus-visible:ring-2 focus-visible:ring-ring"
                href={links.batch(batch.id)}
                key={batch.id}
                prefetch
              >
                <span className="truncate text-muted-foreground">{batch.name}</span>
                <ProgressRing
                  className="shrink-0 gap-1.5 [&_span]:text-[13px]"
                  value={batch.progress}
                />
              </Link>
            ))}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
