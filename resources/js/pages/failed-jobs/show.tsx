import { Head, Link, router } from "@inertiajs/react";
import {
  BracesIcon,
  CircleAlertIcon,
  DatabaseIcon,
  RotateCcwIcon,
  type LucideIcon,
} from "lucide-react";
import { useState } from "react";

import { FailedJobActionsMenu } from "@/components/jobs/failed-job-actions";
import { JobStatus } from "@/components/jobs/job-status";
import { JobTags } from "@/components/jobs/job-tags";
import { StackTrace } from "@/components/jobs/stack-trace";
import { JsonPayload } from "@/components/payload/json-payload";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { show as failedJobShow } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { show as batchShow } from "@/generated/routes/horizon-new-dawn/batches";
import { show as jobShow } from "@/generated/routes/horizon-new-dawn/jobs";
import { show as queueShow } from "@/generated/routes/horizon-new-dawn/queues";
import { useActiveTabQuery } from "@/hooks/use-active-tab-query";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { formatRuntime } from "@/lib/format-duration";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { isInteractiveTarget } from "@/lib/interactive-target";
import { currentQueryParameter } from "@/lib/url-query";
import type { FailedJobDetailPageProps, FailedJobRetry } from "@/types/jobs";

type FailedJobDataTab = "exception" | "context" | "data" | "tags" | "retries";

const dateFormatter = new Intl.DateTimeFormat("sv-SE", {
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
  hour12: false,
});

function timestamp(value: number | null) {
  return value === null ? "—" : dateFormatter.format(value * 1000);
}

function FailedJobShow({ horizon, job }: FailedJobDetailPageProps) {
  const { autoLoad } = useAutoLoadPreference();

  usePageRefresh(horizon.pollInterval, failedJobRefreshProps, autoLoad);

  const retryOfUrl = job.retryOf
    ? resolveHorizonRoute(failedJobShow(job.retryOf), horizon.baseUrl).url
    : null;
  const batchUrl = job.batchId
    ? resolveHorizonRoute(batchShow(job.batchId), horizon.baseUrl).url
    : null;
  const queueUrl = resolveHorizonRoute(
    queueShow(encodeURIComponent(job.queue)),
    horizon.baseUrl,
  ).url;
  const waitTime =
    job.pushedAt !== null && job.reservedAt !== null
      ? Math.max(0, job.reservedAt - job.pushedAt)
      : null;
  const details: Array<{ label: string; value: React.ReactNode; identifier?: boolean }> = [
    { label: "Status", value: <JobStatus status="failed" /> },
    { label: "ID", value: job.id, identifier: true },
    { label: "Connection", value: job.connection },
    {
      label: "Queue",
      value: (
        <Link
          className="text-foreground underline decoration-foreground/40 underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
          href={queueUrl}
          prefetch
        >
          {job.queue}
        </Link>
      ),
    },
    { label: "Attempts", value: String(job.attempts) },
    ...(job.batchId && batchUrl
      ? [
          {
            label: "Batch",
            value: (
              <Link
                className="text-sm text-foreground underline decoration-foreground/40 underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
                href={batchUrl}
                prefetch
              >
                {job.batchId}
              </Link>
            ),
          },
        ]
      : []),
    ...(retryOfUrl
      ? [
          {
            label: "Retry of ID",
            value: (
              <Link
                className="text-sm text-foreground underline decoration-foreground/40 underline-offset-4 transition-colors hover:text-primary hover:decoration-primary"
                href={retryOfUrl}
                prefetch
                title={job.retryOf ?? undefined}
              >
                {job.retryOf}
              </Link>
            ),
          },
        ]
      : []),
    { label: "Created at", value: timestamp(job.pushedAt) },
    ...(job.scheduledAt !== null
      ? [{ label: "Scheduled at", value: timestamp(job.scheduledAt) }]
      : []),
    ...(job.originalScheduledAt !== null && job.originalScheduledAt !== job.scheduledAt
      ? [{ label: "Originally scheduled at", value: timestamp(job.originalScheduledAt) }]
      : []),
    ...(job.reservedAt !== null
      ? [{ label: "Reserved at", value: timestamp(job.reservedAt) }]
      : []),
    ...(waitTime !== null ? [{ label: "Wait time", value: formatRuntime(waitTime) }] : []),
    { label: "Failed at", value: timestamp(job.failedAt) },
    ...(job.runtime !== null ? [{ label: "Runtime", value: formatRuntime(job.runtime) }] : []),
  ];

  return (
    <>
      <Head title="Failed Job Detail" />
      <div className="flex flex-col gap-3.5">
        <Card>
          <CardHeader className="flex h-[54px] flex-row justify-between gap-4 px-6 py-0">
            <CardTitle className="min-w-0 truncate" title={job.name}>
              {job.name}
            </CardTitle>
            <div className="flex shrink-0 items-center">
              <FailedJobActionsMenu
                jobId={job.id}
                horizonBaseUrl={horizon.baseUrl}
                canRetry={job.retryEligible}
              />
            </div>
          </CardHeader>
          <CardContent className="p-0">
            <dl className="pt-1.5 pb-2.5">
              {details.map((detail, index) => (
                <Detail key={detail.label} detail={detail} bordered={index > 0} />
              ))}
            </dl>
          </CardContent>
        </Card>

        <FailedJobDataTabs job={job} horizonBaseUrl={horizon.baseUrl} />
      </div>
    </>
  );
}

function Detail({
  detail,
  bordered,
}: {
  detail: { label: string; value: React.ReactNode; identifier?: boolean };
  bordered: boolean;
}) {
  const title = typeof detail.value === "string" ? detail.value : undefined;

  return (
    <div
      className={
        bordered
          ? "flex gap-4 border-t border-dashed border-separator px-6 py-2.5"
          : "flex gap-4 px-6 py-2.5"
      }
    >
      <dt className="w-40 shrink-0 text-muted-foreground">{detail.label}</dt>
      <dd className={`min-w-0 break-all ${detail.identifier ? "text-sm" : ""}`} title={title}>
        {detail.value}
      </dd>
    </div>
  );
}

function FailedJobDataTabs({
  job,
  horizonBaseUrl,
}: Pick<FailedJobDetailPageProps, "job"> & { horizonBaseUrl: string }) {
  const [activeTab, setActiveTab] = useState<FailedJobDataTab>(initialFailedJobDataTab);
  const hasContext = Object.keys(job.context).length > 0;
  const hasData = Object.keys(job.payload).length > 0;

  useActiveTabQuery(activeTab);

  return (
    <Card>
      <CardContent className="p-0">
        <Tabs
          value={activeTab}
          onValueChange={(value) => setActiveTab(value as FailedJobDataTab)}
          className="gap-0"
        >
          <div className="sticky top-0 z-10 border-b border-separator bg-card">
            <TabsList
              variant="line"
              aria-label="Failed job data"
              className="max-w-full justify-start gap-2 overflow-x-auto rounded-none px-3 py-0"
            >
              <TabsTrigger
                value="exception"
                className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
              >
                Exception
              </TabsTrigger>
              <TabsTrigger
                value="context"
                className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
              >
                Context
              </TabsTrigger>
              <TabsTrigger
                value="data"
                className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
              >
                Data
              </TabsTrigger>
              <TabsTrigger
                value="tags"
                className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
              >
                Tags
                <Badge className="h-4 min-w-4 px-1.5 text-[10.5px]" variant="secondary">
                  {job.tags.length}
                </Badge>
              </TabsTrigger>
              <TabsTrigger
                value="retries"
                className="h-auto flex-none rounded-none px-3 py-4 text-[13.5px]"
              >
                Retries
                <Badge className="h-4 min-w-4 px-1.5 text-[10.5px]" variant="secondary">
                  {job.retriedBy.length}
                </Badge>
              </TabsTrigger>
            </TabsList>
          </div>

          <TabsContent value="exception" className="mt-0">
            {job.exception.trim() ? (
              <StackTrace value={job.exception} />
            ) : (
              <DataEmptyState
                icon={CircleAlertIcon}
                title="No exception details"
                description="Horizon did not retain an exception message for this failed job."
              />
            )}
          </TabsContent>

          <TabsContent value="context" className="mt-0">
            {hasContext ? (
              <JsonPayload value={job.context} />
            ) : (
              <DataEmptyState
                icon={BracesIcon}
                title="No exception context"
                description="This failure did not include any additional exception context."
              />
            )}
          </TabsContent>

          <TabsContent value="data" className="mt-0">
            {hasData ? (
              <JsonPayload value={job.payload} />
            ) : (
              <DataEmptyState
                icon={DatabaseIcon}
                title="No job data"
                description="The retained job payload does not contain any data."
              />
            )}
          </TabsContent>

          <TabsContent value="tags" className="mt-0 px-6 py-4">
            <JobTags tags={job.tags} />
          </TabsContent>

          <TabsContent value="retries" className="mt-0">
            {job.retriedBy.length > 0 ? (
              <RecentRetries retries={job.retriedBy} horizonBaseUrl={horizonBaseUrl} />
            ) : (
              <DataEmptyState
                icon={RotateCcwIcon}
                title="No retry attempts"
                description="This failed job has not been retried."
              />
            )}
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}

function initialFailedJobDataTab(): FailedJobDataTab {
  const requestedTab = currentQueryParameter("tab");

  if (
    requestedTab === "exception" ||
    requestedTab === "context" ||
    requestedTab === "data" ||
    requestedTab === "tags" ||
    requestedTab === "retries"
  ) {
    return requestedTab;
  }

  return "exception";
}

function DataEmptyState({
  icon: Icon,
  title,
  description,
}: {
  icon: LucideIcon;
  title: string;
  description: string;
}) {
  return (
    <Empty className="min-h-48">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon aria-hidden />
        </EmptyMedia>
        <EmptyTitle>{title}</EmptyTitle>
        <EmptyDescription>{description}</EmptyDescription>
      </EmptyHeader>
    </Empty>
  );
}

function RecentRetries({
  retries,
  horizonBaseUrl,
}: {
  retries: readonly FailedJobRetry[];
  horizonBaseUrl: string;
}) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="bg-thead px-6 text-xs text-muted-foreground">Status</TableHead>
          <TableHead className="bg-thead px-6 text-xs text-muted-foreground">ID</TableHead>
          <TableHead className="bg-thead px-6 text-right text-xs text-muted-foreground">
            Retry Time
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {retries.map((retry) => {
          const detailUrl = retryDetailUrl(retry, horizonBaseUrl);

          return (
            <TableRow
              className={detailUrl ? "cursor-pointer" : undefined}
              key={retry.id}
              onClick={(event) => {
                if (!detailUrl || isInteractiveTarget(event.target)) {
                  return;
                }

                router.visit(detailUrl);
              }}
              onMouseEnter={() => {
                if (detailUrl) {
                  router.prefetch(detailUrl);
                }
              }}
            >
              <TableCell className="px-6">
                <RetryStatus status={retry.status} />
              </TableCell>
              <TableCell className="px-6">
                {detailUrl ? (
                  <Link className="text-foreground hover:underline" href={detailUrl} prefetch>
                    {retry.id}
                  </Link>
                ) : (
                  retry.id
                )}
              </TableCell>
              <TableCell className="px-6 text-right text-muted-foreground">
                {timestamp(retry.retriedAt)}
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}

function retryDetailUrl(retry: FailedJobRetry, horizonBaseUrl: string): string | null {
  if (retry.status === "failed") {
    return resolveHorizonRoute(failedJobShow(retry.id), horizonBaseUrl).url;
  }

  if (retry.status === "pending" || retry.status === "reserved") {
    return resolveHorizonRoute(jobShow({ type: "pending", job: retry.id }), horizonBaseUrl).url;
  }

  if (retry.status === "completed") {
    return resolveHorizonRoute(jobShow({ type: "completed", job: retry.id }), horizonBaseUrl).url;
  }

  return null;
}

function RetryStatus({ status }: { status: string }) {
  if (status === "completed") {
    return <Badge variant="success">Completed</Badge>;
  }

  if (status === "failed") {
    return <Badge variant="destructive">Failed</Badge>;
  }

  return (
    <Badge variant="warning">
      {status[0]?.toUpperCase()}
      {status.slice(1)}
    </Badge>
  );
}

const failedJobRefreshProps = ["job"];

export default FailedJobShow;
