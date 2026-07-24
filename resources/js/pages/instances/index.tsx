import { Head } from "@inertiajs/react";

import { SupervisorsTable } from "@/components/dashboard/supervisors-table";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { resolveProcessPollInterval, useProcessTransitions } from "@/hooks/use-process-transitions";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import type { RunningInstancesPageProps } from "@/types/dashboard";

function RunningInstancesIndex({ horizon, supervisors }: RunningInstancesPageProps) {
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

  usePageRefresh(
    resolveProcessPollInterval(horizon.pollInterval),
    ["supervisors"],
    autoRefreshEnabled || fallbackPolling,
  );

  return (
    <>
      <Head title="Instances" />
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
    </>
  );
}

export default RunningInstancesIndex;
