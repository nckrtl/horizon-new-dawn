import { Head } from "@inertiajs/react";
import { useMemo, useState } from "react";

import {
  emptyMetricFilterValues,
  MetricsFilters,
  type MetricFilterValues,
} from "@/components/metrics/metrics-filters";
import { MetricsTable } from "@/components/metrics/metrics-table";
import { TabbedResultsCard, type TabbedResultsTab } from "@/components/tabbed-results-card";
import { index as metricsIndex } from "@/generated/routes/horizon-new-dawn/metrics";
import { useActiveTabQuery } from "@/hooks/use-active-tab-query";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { MetricsPageProps, MetricType } from "@/types/metrics";

function MetricsIndex({ horizon, type, metrics }: MetricsPageProps) {
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<MetricFilterValues>({ ...emptyMetricFilterValues });
  const visibleMetrics = useMemo(() => {
    const query = search.trim().toLocaleLowerCase();

    return metrics.data.filter(
      (metric) =>
        (query === "" || metric.name.toLocaleLowerCase().includes(query)) &&
        matchesThroughputFilter(metric.throughput, filters.throughput) &&
        matchesRuntimeFilter(metric.runtime, filters.runtime),
    );
  }, [filters, metrics.data, search]);
  const metricTypes: readonly MetricType[] = ["jobs", "queues"];
  const tabs: TabbedResultsTab<MetricType>[] = metricTypes.map((value) => ({
    value,
    label: value === "jobs" ? "Jobs" : "Queues",
    href: resolveHorizonRoute(metricsIndex(value), horizon.baseUrl).url,
    preserveState: true,
  }));
  const singular = type === "jobs" ? "job" : "queue";
  const hasSearch = search.trim().length > 0;
  const hasFilters = Object.values(filters).some((value) => value !== null);
  let emptyDescription: string | undefined;

  useActiveTabQuery(type);

  if (hasSearch && hasFilters) {
    emptyDescription = `No ${singular} metrics match your search and filters.`;
  } else if (hasSearch) {
    emptyDescription = `No ${singular} metrics match your search.`;
  } else if (hasFilters) {
    emptyDescription = `No ${singular} metrics match the current filters.`;
  }

  return (
    <>
      <Head title="Metrics" />
      <TabbedResultsCard
        title="Metrics"
        activeTab={type}
        tabs={tabs}
        tabListLabel="Metrics type"
        contentHeading={`${type === "jobs" ? "Job" : "Queue"} metrics`}
        search={search}
        searchLabel={`Search ${singular} metrics`}
        searchPlaceholder={`Search ${singular} metrics`}
        onSearchChange={setSearch}
        filters={<MetricsFilters type={type} values={filters} onChange={setFilters} />}
      >
        <MetricsTable
          type={type}
          metrics={visibleMetrics}
          horizonBaseUrl={horizon.baseUrl}
          available={metrics.available}
          message={metrics.message}
          emptyTitle={hasSearch || hasFilters ? `No matching ${singular} metrics` : undefined}
          emptyDescription={emptyDescription}
        />
      </TabbedResultsCard>
    </>
  );
}

function matchesThroughputFilter(value: number, filter: MetricFilterValues["throughput"]): boolean {
  if (filter === null) {
    return true;
  }

  return filter === "active" ? value > 0 : value === 0;
}

function matchesRuntimeFilter(value: number, filter: MetricFilterValues["runtime"]): boolean {
  if (filter === null) {
    return true;
  }

  return filter === "subsecond" ? value < 1 : value >= 1;
}

export default MetricsIndex;
