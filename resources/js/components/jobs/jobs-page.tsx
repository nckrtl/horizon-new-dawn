import { TabbedResultsCard, type TabbedResultsTab } from "@/components/tabbed-results-card";
import { index as failedJobsIndex } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { index as jobsIndex } from "@/generated/routes/horizon-new-dawn/jobs";
import { useResolvedNavigationCounts } from "@/hooks/use-navigation-counts";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { JobListType } from "@/types/jobs";

export type JobsTab = JobListType | "failed";

const tabs: Array<{ label: string; value: JobsTab }> = [
  { label: "Pending", value: "pending" },
  { label: "Failed", value: "failed" },
  { label: "Completed", value: "completed" },
  { label: "Silenced", value: "silenced" },
];

export function JobsPage({
  activeTab,
  horizonBaseUrl,
  search,
  searchLabel,
  searchPlaceholder,
  onSearchChange,
  filters,
  actions,
  children,
}: {
  activeTab: JobsTab;
  horizonBaseUrl: string;
  search: string;
  searchLabel: string;
  searchPlaceholder: string;
  onSearchChange: (value: string) => void;
  filters?: React.ReactNode;
  actions?: React.ReactNode;
  children: React.ReactNode;
}) {
  const navigationCounts = useResolvedNavigationCounts();
  const resolvedTabs: TabbedResultsTab<JobsTab>[] = tabs.map((tab) => ({
    ...tab,
    count: navigationCounts?.[tab.value],
    href: tabUrl(tab.value, horizonBaseUrl),
  }));

  return (
    <TabbedResultsCard
      title="Jobs"
      activeTab={activeTab}
      tabs={resolvedTabs}
      tabListLabel="Job status"
      contentHeading={`${tabs.find((tab) => tab.value === activeTab)?.label} jobs`}
      search={search}
      searchLabel={searchLabel}
      searchPlaceholder={searchPlaceholder}
      onSearchChange={onSearchChange}
      filters={filters}
      actions={actions}
    >
      {children}
    </TabbedResultsCard>
  );
}

function tabUrl(tab: JobsTab, horizonBaseUrl: string): string {
  const route = tab === "failed" ? failedJobsIndex() : jobsIndex(tab);

  return resolveHorizonRoute(route, horizonBaseUrl).url;
}
