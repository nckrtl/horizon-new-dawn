import { router, usePoll } from "@inertiajs/react";
import { useEffect, useRef } from "react";

const dashboardProps = ["summary", "workload", "supervisors"];
const emptyProps: readonly string[] = [];

export function usePageRefresh(
  interval: number,
  props: readonly string[],
  enabled: boolean,
  resetProps: readonly string[] = emptyProps,
) {
  const requestOptions = () => ({
    only: [...new Set([...props, "horizon", "navigationCounts"])],
    ...(resetProps.length > 0 ? { reset: [...resetProps] } : {}),
    preserveUrl: true,
    showProgress: false,
  });
  const requestOptionsRef = useRef(requestOptions);
  const wasPollingRef = useRef(enabled && interval > 0);

  requestOptionsRef.current = requestOptions;

  const poll = usePoll(interval, () => requestOptionsRef.current(), {
    autoStart: false,
    mode: "cancel",
  });
  const pollRef = useRef(poll);

  pollRef.current = poll;

  useEffect(() => {
    const controls = pollRef.current;
    const shouldPoll = enabled && interval > 0;

    if (shouldPoll) {
      if (!wasPollingRef.current) {
        router.reload(requestOptionsRef.current());
      }

      controls.start();
    } else {
      controls.stop();
    }

    wasPollingRef.current = shouldPoll;

    return () => controls.stop();
  }, [enabled, interval]);
}

export function useDashboardRefresh(interval: number, enabled: boolean, props = dashboardProps) {
  usePageRefresh(interval, props, enabled);
}
