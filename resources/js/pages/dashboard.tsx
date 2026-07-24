import { Head } from "@inertiajs/react";

import { DashboardOverview } from "@/components/dashboard/dashboard-overview";
import { SupervisorsTable } from "@/components/dashboard/supervisors-table";
import { WorkloadTable } from "@/components/dashboard/workload-table";
import { Duration } from "@/components/duration";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  index as batchesIndex,
  show as batchShow,
} from "@/generated/routes/horizon-new-dawn/batches";
import { index as failedJobsIndex } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { index as jobsIndex } from "@/generated/routes/horizon-new-dawn/jobs";
import { useDashboardRefresh } from "@/hooks/use-dashboard-refresh";
import { resolveProcessPollInterval, useProcessTransitions } from "@/hooks/use-process-transitions";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { DashboardPageProps } from "@/types/dashboard";

function Dashboard({ horizon, summary, workload, supervisors }: DashboardPageProps) {
  const { autoLoad } = useAutoLoadPreference();
  const {
    hasPendingTransitions,
    onInstanceTransition,
    onInstanceTransitionFailure,
    onSupervisorTransition,
    onSupervisorTransitionFailure,
    transitions,
  } = useProcessTransitions(supervisors, horizon.pollInterval);
  const autoRefreshEnabled = autoLoad && horizon.pollInterval > 0;
  const fallbackPolling = !autoRefreshEnabled && hasPendingTransitions;
  const hasWorkload = workload.available && workload.items.length > 0;

  useDashboardRefresh(
    resolveProcessPollInterval(horizon.pollInterval),
    autoRefreshEnabled || fallbackPolling,
  );

  const route = (definition: { url: string }) =>
    resolveHorizonRoute(definition, horizon.baseUrl).url;

  return (
    <>
      <Head title="Dashboard" />
      <div className="flex flex-col gap-[7px] min-[1140px]:gap-3.5">
        <DashboardOverview
          summary={summary}
          links={{
            pending: route(jobsIndex("pending")),
            failed: route(failedJobsIndex()),
            completed: route(jobsIndex("completed")),
            batches: route(batchesIndex()),
            batch: (id) => route(batchShow(id)),
          }}
        />

        <Card>
          <CardHeader>
            <CardTitle>Workload</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {hasWorkload ? (
              <div className="grid gap-px border-b border-separator bg-separator sm:grid-cols-2 md:grid-cols-4">
                <WorkloadStat label="Total Processes" value={summary.processes} />
                <WorkloadStat
                  label="Max Wait Time"
                  value={<Duration seconds={summary.maxWaitSeconds} />}
                  detail={summary.maxWaitQueue}
                />
                <WorkloadStat label="Max Runtime" value={summary.queueWithMaxRuntime ?? "—"} />
                <WorkloadStat
                  label="Max Throughput"
                  value={summary.queueWithMaxThroughput ?? "—"}
                />
              </div>
            ) : null}
            <WorkloadTable
              workload={workload}
              horizonBaseUrl={horizon.baseUrl}
              queuePausing={horizon.capabilities?.queuePausing ?? false}
            />
          </CardContent>
        </Card>

        {supervisors ? (
          <SupervisorsTable
            autoRefreshEnabled={autoRefreshEnabled}
            supervisors={supervisors}
            horizonBaseUrl={horizon.baseUrl}
            transitions={transitions}
            onInstanceTransition={onInstanceTransition}
            onInstanceTransitionFailure={onInstanceTransitionFailure}
            onSupervisorTransition={onSupervisorTransition}
            onSupervisorTransitionFailure={onSupervisorTransitionFailure}
          />
        ) : null}
      </div>
    </>
  );
}

function WorkloadStat({
  label,
  value,
  detail,
}: {
  label: string;
  value: React.ReactNode;
  detail?: string | null;
}) {
  return (
    <div className="bg-card px-6 py-4">
      <p className="text-[13px] font-medium text-muted-foreground">{label}</p>
      <p className="mt-3 text-[1.375rem] font-semibold tracking-tight tabular-nums">{value}</p>
      {detail ? <p className="mt-0.5 text-[13px] text-muted-foreground">({detail})</p> : null}
    </div>
  );
}

export default Dashboard;
