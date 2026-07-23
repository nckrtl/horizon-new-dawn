import { Head } from "@inertiajs/react";

import { SupervisorsTable } from "@/components/dashboard/supervisors-table";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import type { RunningInstancesPageProps } from "@/types/dashboard";

function RunningInstancesIndex({ horizon, supervisors }: RunningInstancesPageProps) {
  const { autoLoad } = useAutoLoadPreference();

  usePageRefresh(horizon.pollInterval, ["supervisors"], autoLoad);

  return (
    <>
      <Head title="Instances" />
      <SupervisorsTable
        autoRefreshEnabled={autoLoad}
        supervisors={supervisors}
        horizonBaseUrl={horizon.baseUrl}
      />
    </>
  );
}

export default RunningInstancesIndex;
