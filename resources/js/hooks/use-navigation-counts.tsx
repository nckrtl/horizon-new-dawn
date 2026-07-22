import { usePage } from "@inertiajs/react";
import { createContext, type ReactNode, useContext } from "react";

import type { HorizonPageProps, NavigationCounts } from "@/types/page";

const NavigationCountsContext = createContext<NavigationCounts | undefined>(undefined);

export function NavigationCountsProvider({
  children,
  value,
}: {
  children: ReactNode;
  value?: NavigationCounts;
}) {
  return (
    <NavigationCountsContext.Provider value={value}>{children}</NavigationCountsContext.Provider>
  );
}

export function useResolvedNavigationCounts() {
  const resolvedNavigationCounts = useContext(NavigationCountsContext);
  const { navigationCounts } = usePage<HorizonPageProps>().props;

  return resolvedNavigationCounts ?? navigationCounts;
}
