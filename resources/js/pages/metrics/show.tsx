import { Head } from "@inertiajs/react";
import { TriangleAlertIcon } from "lucide-react";

import { MetricChart } from "@/components/metrics/metric-chart";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import type { MetricPreviewPageProps } from "@/types/metrics";

function MetricShow({ horizon, name, preview }: MetricPreviewPageProps) {
  const { autoLoad } = useAutoLoadPreference();

  usePageRefresh(horizon.pollInterval, metricRefreshProps, autoLoad);

  return (
    <>
      <Head title={`Metrics for ${name}`} />
      <div className="flex flex-col gap-3.5">
        {!preview.available ? (
          <Alert variant="destructive">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertTitle>Metrics unavailable</AlertTitle>
            <AlertDescription>
              {preview.message ?? "Metrics for this item are currently unavailable."}
            </AlertDescription>
          </Alert>
        ) : null}
        <MetricCard title={`Throughput — ${name}`}>
          <MetricChart kind="throughput" snapshots={preview.data} />
        </MetricCard>
        <MetricCard title={`Runtime — ${name}`}>
          <MetricChart kind="runtime" snapshots={preview.data} />
        </MetricCard>
      </div>
    </>
  );
}

function MetricCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="truncate" title={title}>
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent className="px-0 pt-3 pb-2">{children}</CardContent>
    </Card>
  );
}

const metricRefreshProps = ["preview"];

export default MetricShow;
