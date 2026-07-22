import { usePage } from "@inertiajs/react";
import { MenuIcon, TriangleAlertIcon, XIcon } from "lucide-react";
import {
  createContext,
  type CSSProperties,
  type ReactNode,
  useCallback,
  useContext,
  useMemo,
  useState,
} from "react";
import { useEffect } from "react";
import { toast } from "sonner";

import { AppSidebar } from "@/components/app-sidebar";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { SidebarInset, SidebarProvider, SidebarTrigger, useSidebar } from "@/components/ui/sidebar";
import { NavigationCountsProvider } from "@/hooks/use-navigation-counts";
import { cn } from "@/lib/utils";
import type { HorizonPageProps, NavigationCounts } from "@/types/page";

type AutoLoadContextValue = {
  autoLoad: boolean;
};

const AutoLoadContext = createContext<AutoLoadContextValue>({ autoLoad: false });
const AUTO_LOAD_STORAGE_KEY = "horizonAutoLoadsNewEntries";
const NAVIGATION_COUNTS_STORAGE_KEY = "horizonNavigationCounts";
const NAVIGATION_COUNTS_CACHE_TTL = 5 * 60 * 1000;
const navigationCountKeys: Array<keyof NavigationCounts> = [
  "instances",
  "monitoring",
  "metrics",
  "queues",
  "batches",
  "pending",
  "completed",
  "silenced",
  "failed",
];

function storedAutoLoadPreference() {
  try {
    return localStorage.getItem(AUTO_LOAD_STORAGE_KEY) === "1";
  } catch {
    return false;
  }
}

function storedNavigationCounts(baseUrl: string): NavigationCounts | undefined {
  try {
    const cached = localStorage.getItem(navigationCountsStorageKey(baseUrl));

    if (cached === null) {
      return undefined;
    }

    const parsed: unknown = JSON.parse(cached);

    if (!isStoredNavigationCounts(parsed) || parsed.expiresAt <= Date.now()) {
      localStorage.removeItem(navigationCountsStorageKey(baseUrl));

      return undefined;
    }

    return parsed.counts;
  } catch {
    return undefined;
  }
}

function storeNavigationCounts(baseUrl: string, counts: NavigationCounts) {
  try {
    localStorage.setItem(
      navigationCountsStorageKey(baseUrl),
      JSON.stringify({
        counts,
        expiresAt: Date.now() + NAVIGATION_COUNTS_CACHE_TTL,
      }),
    );
  } catch {
    // The current session can still use the resolved counts when storage is unavailable.
  }
}

function navigationCountsStorageKey(baseUrl: string) {
  return `${NAVIGATION_COUNTS_STORAGE_KEY}:${baseUrl}`;
}

function isStoredNavigationCounts(
  value: unknown,
): value is { counts: NavigationCounts; expiresAt: number } {
  if (typeof value !== "object" || value === null) {
    return false;
  }

  const cached = value as Record<string, unknown>;
  const counts = cached.counts;

  return (
    typeof cached.expiresAt === "number" &&
    typeof counts === "object" &&
    counts !== null &&
    navigationCountKeys.every((key) => {
      const count = (counts as Record<string, unknown>)[key];

      return typeof count === "number" || count === null;
    })
  );
}

export function useAutoLoadPreference() {
  return useContext(AutoLoadContext);
}

function MobileSidebarTrigger() {
  const { openMobile } = useSidebar();

  return (
    <SidebarTrigger
      variant="default"
      aria-expanded={openMobile}
      className="fixed right-[7px] bottom-[7px] z-[60] size-11 rounded-full shadow-lg min-[1140px]:hidden"
    >
      <span className="relative size-5" aria-hidden="true">
        <MenuIcon
          className={cn(
            "absolute inset-0 size-5 transition-[opacity,rotate,scale] duration-200 ease-out motion-reduce:transition-none",
            openMobile ? "rotate-90 scale-75 opacity-0" : "rotate-0 scale-100 opacity-100",
          )}
        />
        <XIcon
          className={cn(
            "absolute inset-0 size-5 transition-[opacity,rotate,scale] duration-200 ease-out motion-reduce:transition-none",
            openMobile ? "rotate-0 scale-100 opacity-100" : "-rotate-90 scale-75 opacity-0",
          )}
        />
      </span>
    </SidebarTrigger>
  );
}

export function HorizonLayout({ children }: { children: ReactNode }) {
  const { flash, horizon, meta, navigationCounts } = usePage<HorizonPageProps>().props;
  const [autoLoad, setAutoLoad] = useState(storedAutoLoadPreference);
  const [resolvedNavigationCounts, setResolvedNavigationCounts] = useState(
    () => navigationCounts ?? storedNavigationCounts(horizon.baseUrl),
  );
  const autoLoadValue = useMemo(() => ({ autoLoad }), [autoLoad]);
  const updateAutoLoad = useCallback((enabled: boolean) => {
    setAutoLoad(enabled);

    try {
      localStorage.setItem(AUTO_LOAD_STORAGE_KEY, enabled ? "1" : "0");
    } catch {
      // The current session can still use the preference when storage is unavailable.
    }
  }, []);

  useEffect(() => {
    if (navigationCounts !== undefined) {
      setResolvedNavigationCounts(navigationCounts);
      storeNavigationCounts(horizon.baseUrl, navigationCounts);
    }
  }, [horizon.baseUrl, navigationCounts]);

  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }

    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash?.error, flash?.success]);

  return (
    <AutoLoadContext.Provider value={autoLoadValue}>
      <NavigationCountsProvider value={resolvedNavigationCounts}>
        <SidebarProvider
          className="relative mx-auto max-w-[1400px] min-[1140px]:min-w-[1140px]"
          style={
            {
              "--horizon-sidebar-offset": "max(0px, calc((100vw - 1400px) / 2))",
            } as CSSProperties
          }
        >
          <AppSidebar
            activeNavigation={meta.activeNavigation}
            horizonBaseUrl={horizon.baseUrl}
            status={horizon.status}
            processing={horizon.processing}
            autoLoad={autoLoad}
            onAutoLoadChange={updateAutoLoad}
            navigationCounts={resolvedNavigationCounts}
          />
          <SidebarInset className="min-w-0 overflow-clip">
            <div className="flex w-full flex-1 flex-col gap-3.5 p-[7px] min-[1140px]:pt-3.5 min-[1140px]:pr-3.5 min-[1140px]:pb-3.5 min-[1140px]:pl-6">
              {horizon.maintenanceMode ? (
                <Alert className="mb-4 border-warn/30">
                  <TriangleAlertIcon className="text-warn" aria-hidden="true" />
                  <AlertTitle>Application maintenance mode</AlertTitle>
                  <AlertDescription>
                    Queued jobs may not be processed unless the worker is using the force flag.
                  </AlertDescription>
                </Alert>
              ) : null}
              {children}
            </div>
          </SidebarInset>
          <MobileSidebarTrigger />
        </SidebarProvider>
      </NavigationCountsProvider>
    </AutoLoadContext.Provider>
  );
}
