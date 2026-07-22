import { Head } from "@inertiajs/react";
import { useEffect, useRef } from "react";

import { QueueActivityTabs } from "@/components/queues/queue-activity-tabs";
import { QueueOverview } from "@/components/queues/queue-overview";
import { usePageRefresh } from "@/hooks/use-dashboard-refresh";
import { useAutoLoadPreference } from "@/layouts/horizon-layout";
import type { QueueShowPageProps } from "@/types/queues";

function QueueShow({ horizon, queue, view, summary, tab, preview, activity }: QueueShowPageProps) {
  const { autoLoad } = useAutoLoadPreference();
  const focusedUrl = useRef<string | null>(null);

  usePageRefresh(
    horizon.pollInterval,
    view === "metrics" ? metricRefreshProps : overviewRefreshProps,
    autoLoad,
    activityResetProps,
  );

  useEffect(() => {
    if (window.location.hash !== "#queue-activity") {
      return;
    }

    const url = `${window.location.pathname}${window.location.search}${window.location.hash}`;

    if (focusedUrl.current === url) {
      return;
    }

    focusedUrl.current = url;
    const frame = window.requestAnimationFrame(() => {
      const target = document.getElementById("queue-activity");

      if (!target) {
        return;
      }

      target.focus({ preventScroll: true });
      target.scrollIntoView({
        behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
        block: "start",
      });
    });

    return () => window.cancelAnimationFrame(frame);
  }, [activity.data.length, tab]);

  return (
    <>
      <Head title={queue} />
      <div className="flex flex-col gap-[7px] min-[1140px]:gap-3.5">
        <QueueOverview
          view={view}
          tab={tab}
          summary={summary}
          preview={preview}
          horizonBaseUrl={horizon.baseUrl}
          queuePausing={horizon.capabilities?.queuePausing ?? false}
        />
        <QueueActivityTabs
          key={`${queue}:${tab}`}
          queue={queue}
          tab={tab}
          view={view}
          summary={summary}
          activity={activity}
          horizonBaseUrl={horizon.baseUrl}
          queuePausing={horizon.capabilities?.queuePausing ?? false}
        />
      </div>
    </>
  );
}

const overviewRefreshProps = ["summary", "activity"];
const metricRefreshProps = ["summary", "activity", "preview"];
const activityResetProps = ["activity"];

export default QueueShow;
