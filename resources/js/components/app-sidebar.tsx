import { Link } from "@inertiajs/react";

import {
  BatchesNavigationIcon,
  DashboardNavigationIcon,
  JobsNavigationIcon,
  MetricsNavigationIcon,
  MonitoringNavigationIcon,
  QueueNavigationIcon,
  RunningInstancesNavigationIcon,
} from "@/components/navigation-icons";
import { HorizonStatus } from "@/components/shell/horizon-status";
import { SidebarFooterControls } from "@/components/shell/page-header";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar";
import { dashboard } from "@/generated/routes/horizon-new-dawn";
import { index as batchesIndex } from "@/generated/routes/horizon-new-dawn/batches";
import { index as jobsIndex } from "@/generated/routes/horizon-new-dawn/jobs";
import { index as metricsIndex } from "@/generated/routes/horizon-new-dawn/metrics";
import { index as monitoringIndex } from "@/generated/routes/horizon-new-dawn/monitoring";
import { index as runningInstancesIndex } from "@/generated/routes/horizon-new-dawn/instances";
import { index as queuesIndex } from "@/generated/routes/horizon-new-dawn/queues";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { HorizonStatus as HorizonStatusValue } from "@/types/dashboard";
import type { HorizonNavigation, NavigationCounts } from "@/types/page";

type NavigationEntry = {
  label: string;
  active: HorizonNavigation[];
  icon: typeof DashboardNavigationIcon;
  count: keyof NavigationCounts | Array<keyof NavigationCounts> | null;
  route: () => { url: string };
};

const navigation: NavigationEntry[] = [
  {
    label: "Dashboard",
    active: ["dashboard"],
    icon: DashboardNavigationIcon,
    count: null,
    route: () => dashboard(),
  },
  {
    label: "Monitoring",
    active: ["monitoring"],
    icon: MonitoringNavigationIcon,
    count: "monitoring",
    route: () => monitoringIndex(),
  },
  {
    label: "Metrics",
    active: ["metrics"],
    icon: MetricsNavigationIcon,
    count: "metrics",
    route: () => metricsIndex("jobs"),
  },
  {
    label: "Instances",
    active: ["instances"],
    icon: RunningInstancesNavigationIcon,
    count: "instances",
    route: () => runningInstancesIndex(),
  },
  {
    label: "Queues",
    active: ["queues"],
    icon: QueueNavigationIcon,
    count: "queues",
    route: () => queuesIndex(),
  },
  {
    label: "Batches",
    active: ["batches"],
    icon: BatchesNavigationIcon,
    count: "batches",
    route: () => batchesIndex(),
  },
  {
    label: "Jobs",
    active: ["pending", "failed", "completed", "silenced"],
    icon: JobsNavigationIcon,
    count: ["pending", "failed", "completed", "silenced"],
    route: () => jobsIndex("pending"),
  },
];

export function AppSidebar({
  activeNavigation,
  horizonBaseUrl,
  status,
  processing,
  autoLoad,
  onAutoLoadChange,
  navigationCounts,
}: {
  activeNavigation: HorizonNavigation;
  horizonBaseUrl: string;
  status?: HorizonStatusValue;
  processing?: boolean;
  autoLoad?: boolean;
  onAutoLoadChange?: (enabled: boolean) => void;
  navigationCounts?: NavigationCounts;
}) {
  const { isMobile, setOpenMobile } = useSidebar();
  const closeMobileSidebar = () => setOpenMobile(false);

  return (
    <Sidebar
      collapsible="offcanvas"
      className="!absolute !left-0 !h-full !border-r-0 !border-l-0 group-data-[collapsible=offcanvas]:!-left-(--sidebar-width) min-[1140px]:!fixed min-[1140px]:!inset-y-0 min-[1140px]:!left-(--horizon-sidebar-offset) min-[1140px]:!h-svh"
    >
      <SidebarHeader className="h-16 flex-row items-center justify-between gap-3 pt-0 pr-[19px] pb-0 pl-3 min-[1140px]:h-[4.8rem] min-[1140px]:pt-[5px]">
        <Link
          className="flex min-w-0 items-center gap-2 pl-2 text-sm tracking-[-0.01em] outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
          href={resolveHorizonRoute(dashboard(), horizonBaseUrl).url}
          prefetch
          cacheFor={["5s", "5m"]}
          aria-label="Laravel Horizon"
          onClick={closeMobileSidebar}
        >
          <svg viewBox="0 0 30 30" className="size-5 shrink-0 fill-[#7746ec]" aria-hidden="true">
            <path d="M5.26176342 26.4094389C2.04147988 23.6582233 0 19.5675182 0 15c0-4.1421356 1.67893219-7.89213562 4.39339828-10.60660172C7.10786438 1.67893219 10.8578644 0 15 0c8.2842712 0 15 6.71572875 15 15 0 8.2842712-6.7157288 15-15 15-3.716753 0-7.11777662-1.3517984-9.73823658-3.5905611zM4.03811305 15.9222506C5.70084247 14.4569342 6.87195416 12.5 10 12.5c5 0 5 5 10 5 3.1280454 0 4.2991572-1.9569336 5.961887-3.4222502C25.4934253 8.43417206 20.7645408 4 15 4 8.92486775 4 4 8.92486775 4 15c0 .3105915.01287248.6181765.03811305.9222506z" />
          </svg>
          <span>
            <strong className="font-bold">Laravel</strong> Horizon
          </span>
        </Link>
        {status ? (
          <HorizonStatus status={status} processing={processing} showTooltip={!isMobile} />
        ) : null}
      </SidebarHeader>

      <SidebarContent className="py-0">
        <SidebarGroup className="px-3 pt-0 pb-3">
          <SidebarGroupLabel className="px-2">Pages</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu className="gap-1.5">
              {navigation.map((item) => {
                const isActive = item.active.includes(activeNavigation);
                const url = resolveHorizonRoute(item.route(), horizonBaseUrl).url;
                const count = navigationCount(item.count, navigationCounts);

                return (
                  <SidebarMenuItem key={item.label}>
                    <SidebarMenuButton
                      className="h-9 gap-2.5 rounded-lg border-t border-transparent px-2.5 text-sm font-normal data-active:border-nav-top data-active:bg-sidebar-accent data-active:text-sidebar-accent-foreground data-active:font-medium"
                      isActive={isActive}
                      tooltip={item.label}
                      aria-current={isActive ? "page" : undefined}
                      render={
                        <Link
                          href={url}
                          prefetch={item.active.includes("dashboard") ? ["mount", "hover"] : true}
                          cacheFor={item.active.includes("dashboard") ? ["5s", "5m"] : undefined}
                          onClick={closeMobileSidebar}
                        />
                      }
                    >
                      <item.icon
                        className={
                          isActive ? "size-3.5 text-primary" : "size-3.5 text-muted-foreground"
                        }
                        strokeWidth={1.75}
                        aria-hidden="true"
                      />
                      <span>{item.label}</span>
                    </SidebarMenuButton>
                    {count !== null ? (
                      <SidebarMenuBadge className="!top-1/2 -translate-x-[2px] -translate-y-1/2 font-normal !text-muted-foreground opacity-60">
                        {count}
                      </SidebarMenuBadge>
                    ) : null}
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      {status && autoLoad !== undefined && onAutoLoadChange ? (
        <SidebarFooter className="gap-0 px-3 pb-3">
          <SidebarFooterControls
            status={status}
            autoLoad={autoLoad}
            onAutoLoadChange={onAutoLoadChange}
          />
        </SidebarFooter>
      ) : null}
    </Sidebar>
  );
}

function navigationCount(
  keys: keyof NavigationCounts | Array<keyof NavigationCounts> | null,
  counts?: NavigationCounts,
): number | null {
  if (keys === null || counts === undefined) {
    return null;
  }

  const values = (Array.isArray(keys) ? keys : [keys]).map((key) => counts[key]);
  const available = values.filter((value): value is number => value !== null);

  return available.length === 0 ? null : available.reduce((total, value) => total + value, 0);
}
