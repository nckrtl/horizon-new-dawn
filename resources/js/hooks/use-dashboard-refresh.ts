import { usePoll } from "@inertiajs/react";
import { useEffect, useRef } from "react";

const dashboardProps = ["summary", "workload"];
const emptyProps: readonly string[] = [];

export function usePageRefresh(
  interval: number,
  props: readonly string[],
  enabled: boolean,
  resetProps: readonly string[] = emptyProps,
) {
  const poll = usePoll(
    interval,
    () => ({
      only: [...new Set([...props, "horizon", "navigationCounts"])],
      ...(resetProps.length > 0 ? { reset: [...resetProps] } : {}),
      preserveUrl: true,
      showProgress: false,
    }),
    { autoStart: false, mode: "cancel" },
  );
  const pollRef = useRef(poll);

  pollRef.current = poll;

  useEffect(() => {
    const controls = pollRef.current;

    if (enabled && interval > 0) {
      controls.start();
    } else {
      controls.stop();
    }

    return () => controls.stop();
  }, [enabled, interval]);
}

export function useDashboardRefresh(interval: number, enabled: boolean, props = dashboardProps) {
  usePageRefresh(interval, props, enabled);
}
