import { useEffect } from "react";

import { replaceCurrentQuery } from "@/lib/url-query";

export function useActiveTabQuery(tab: string) {
  useEffect(() => {
    const sync = () => replaceCurrentQuery({ tab });
    const timeout = window.setTimeout(sync, 0);

    document.addEventListener("inertia:navigate", sync);

    return () => {
      window.clearTimeout(timeout);
      document.removeEventListener("inertia:navigate", sync);
    };
  }, [tab]);
}
