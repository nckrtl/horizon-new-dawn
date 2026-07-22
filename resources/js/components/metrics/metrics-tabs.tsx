import { Link } from "@inertiajs/react";

import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { index as metricsIndex } from "@/generated/routes/horizon-new-dawn/metrics";
import { useActiveTabQuery } from "@/hooks/use-active-tab-query";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { MetricType } from "@/types/metrics";

export function MetricsTabs({
  type,
  horizonBaseUrl,
}: {
  type: MetricType;
  horizonBaseUrl: string;
}) {
  const route = (nextType: MetricType) =>
    resolveHorizonRoute(metricsIndex(nextType), horizonBaseUrl).url;

  useActiveTabQuery(type);

  return (
    <Tabs value={type} className="gap-0">
      <TabsList
        variant="line"
        className="relative w-full justify-start gap-2 rounded-none px-4 py-0 before:pointer-events-none before:absolute before:inset-x-0 before:bottom-0 before:h-px before:bg-separator"
        aria-label="Metrics type"
      >
        <TabsTrigger
          className="h-auto flex-none rounded-none px-3 py-2.5 text-[13.5px]"
          value="jobs"
          nativeButton={false}
          render={<Link href={route("jobs")} prefetch preserveState />}
        >
          Jobs
        </TabsTrigger>
        <TabsTrigger
          className="h-auto flex-none rounded-none px-3 py-2.5 text-[13.5px]"
          value="queues"
          nativeButton={false}
          render={<Link href={route("queues")} prefetch preserveState />}
        >
          Queues
        </TabsTrigger>
      </TabsList>
    </Tabs>
  );
}
