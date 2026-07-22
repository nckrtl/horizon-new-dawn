import { Link } from "@inertiajs/react";

import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { show as monitoringShow } from "@/generated/routes/horizon-new-dawn/monitoring";
import { useActiveTabQuery } from "@/hooks/use-active-tab-query";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import { urlWithCurrentQuery } from "@/lib/url-query";
import type { MonitoringStatus } from "@/types/monitoring";

const numberFormatter = new Intl.NumberFormat();

export function MonitoringTabs({
  tag,
  status,
  horizonBaseUrl,
  trackedCount,
  failedCount,
  jobFilter,
  queueFilter,
}: {
  tag: string;
  status: MonitoringStatus;
  horizonBaseUrl: string;
  trackedCount: number;
  failedCount: number;
  jobFilter: string | null;
  queueFilter: string | null;
}) {
  const route = (nextStatus: MonitoringStatus) => {
    const destination = resolveHorizonRoute(
      monitoringShow({ tag: encodeURIComponent(tag), status: nextStatus }),
      horizonBaseUrl,
    ).url;

    return urlWithCurrentQuery(destination, { job: jobFilter, queue: queueFilter }, [
      "starting_at",
      "tab",
    ]);
  };

  useActiveTabQuery(status);

  return (
    <Tabs value={status} className="min-w-0 flex-1 gap-0">
      <TabsList
        variant="line"
        className="relative w-full justify-start gap-2 rounded-none px-4 py-0 before:pointer-events-none before:absolute before:inset-x-0 before:bottom-0 before:hidden before:h-px before:bg-separator"
        aria-label="Monitored tag job status"
      >
        <TabsTrigger
          className="h-auto flex-none rounded-none px-3 py-2.5 text-[13.5px]"
          value="jobs"
          nativeButton={false}
          render={<Link href={route("jobs")} prefetch preserveState />}
        >
          Recent Jobs
          <span className="ml-1.5 tabular-nums text-muted-foreground">
            {numberFormatter.format(trackedCount)}
          </span>
        </TabsTrigger>
        <TabsTrigger
          className="h-auto flex-none rounded-none px-3 py-2.5 text-[13.5px]"
          value="failed"
          nativeButton={false}
          render={<Link href={route("failed")} prefetch preserveState />}
        >
          Failed Jobs
          <span className="ml-1.5 tabular-nums text-muted-foreground">
            {numberFormatter.format(failedCount)}
          </span>
        </TabsTrigger>
      </TabsList>
    </Tabs>
  );
}
