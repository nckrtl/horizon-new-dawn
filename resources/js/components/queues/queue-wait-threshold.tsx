import { Duration } from "@/components/duration";
import { Badge } from "@/components/ui/badge";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type { QueueWaitThreshold, QueueWaitThresholdStatus } from "@/types/queues";

const statusDetails = {
  exceeded: {
    label: "Exceeded",
    variant: "destructive" as const,
  },
  within_bounds: {
    label: "Within bounds",
    variant: "success" as const,
  },
} satisfies Record<Exclude<QueueWaitThresholdStatus, "calculating" | "disabled">, object>;

export const queueWaitThresholdSortRank = {
  exceeded: 0,
  calculating: 1,
  disabled: 2,
  within_bounds: 3,
} satisfies Record<QueueWaitThresholdStatus, number>;

export function QueueWaitThresholdBadge({ status }: { status: QueueWaitThresholdStatus }) {
  if (status === "disabled" || status === "calculating") {
    const description =
      status === "disabled" ? "Wait monitoring disabled" : "Waiting for runtime data";

    return (
      <Tooltip>
        <TooltipTrigger
          render={
            <span
              className="inline-flex h-5 min-w-5 items-center text-sm text-muted-foreground"
              aria-label={description}
              data-status={status}
            >
              —
            </span>
          }
        />
        <TooltipContent>{description}</TooltipContent>
      </Tooltip>
    );
  }

  const details = statusDetails[status];

  return (
    <Badge variant={details.variant} data-status={status}>
      {details.label}
    </Badge>
  );
}

export function QueueWaitThresholdCell({ waitThreshold }: { waitThreshold?: QueueWaitThreshold }) {
  return <QueueWaitThresholdBadge status={waitThreshold?.status ?? "disabled"} />;
}

export function QueueWaitThresholdMetric({ waitThreshold }: { waitThreshold: QueueWaitThreshold }) {
  const multipleConnections = waitThreshold.targets.length > 1;
  const waitLabel = multipleConnections
    ? `Estimated wait (${waitThreshold.decisiveConnection})`
    : "Estimated wait";
  const oldestLabel =
    multipleConnections && waitThreshold.oldestReadyConnection
      ? `Oldest ready (${waitThreshold.oldestReadyConnection})`
      : "Oldest ready";

  return (
    <div className="bg-card px-6 pt-4 pb-2.5">
      <div className="flex items-center justify-between gap-3">
        <p className="text-[13px] font-medium text-muted-foreground">Wait threshold</p>
        <QueueWaitThresholdBadge status={waitThreshold.status} />
      </div>
      <div className="mt-3.5">
        <WaitThresholdDetail
          label={waitLabel}
          value={
            waitThreshold.waitSeconds === null ? (
              "—"
            ) : (
              <Duration seconds={waitThreshold.waitSeconds} />
            )
          }
        />
        <WaitThresholdDetail
          label="Threshold"
          value={
            waitThreshold.status === "disabled" ? (
              "—"
            ) : (
              <Duration seconds={waitThreshold.thresholdSeconds} />
            )
          }
        />
        <WaitThresholdDetail
          label={oldestLabel}
          value={
            waitThreshold.oldestReadyAgeSeconds === null ? (
              "—"
            ) : (
              <Duration seconds={waitThreshold.oldestReadyAgeSeconds} />
            )
          }
        />
      </div>
    </div>
  );
}

function WaitThresholdDetail({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-3 border-t border-dashed border-separator py-[7px] text-[13px]">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-normal text-muted-foreground">{value}</span>
    </div>
  );
}
