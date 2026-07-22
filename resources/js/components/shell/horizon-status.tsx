import { CirclePauseIcon, PowerIcon, TriangleAlertIcon } from "lucide-react";

import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { HorizonStatus as HorizonStatusValue } from "@/types/dashboard";

const radius = 6.5;
const circumference = 2 * Math.PI * radius;
const spinnerProgress = 25;

const statusLabels: Record<HorizonStatusValue, string> = {
  running: "Idle, no jobs to process",
  paused: "Horizon is paused",
  inactive: "Horizon is inactive",
  unavailable: "Horizon is unavailable",
};

const tooltipLabels: Record<Exclude<HorizonStatusValue, "running">, string> = {
  paused: "Paused",
  inactive: "Inactive — run php artisan horizon",
  unavailable: "Unavailable",
};

export function HorizonStatus({
  status,
  processing = false,
  showTooltip = true,
  ariaLabel,
}: {
  status: HorizonStatusValue;
  processing?: boolean;
  showTooltip?: boolean;
  ariaLabel?: string;
}) {
  const isProcessing = status === "running" && processing;
  const label = isProcessing ? "Processing jobs" : statusLabels[status];
  const tooltipLabel =
    status === "running"
      ? isProcessing
        ? "Processing jobs"
        : statusLabels.running
      : tooltipLabels[status];

  const indicator = (
    <div
      className="flex size-4 shrink-0 items-center justify-center text-muted-foreground data-[status=inactive]:opacity-60 data-[status=paused]:text-warn data-[status=unavailable]:text-destructive"
      data-status={status}
      data-activity={status === "running" ? (isProcessing ? "working" : "idle") : undefined}
      role="status"
      aria-label={ariaLabel ?? label}
      tabIndex={showTooltip ? 0 : undefined}
    >
      {status === "running" ? (
        <svg
          viewBox="0 0 16 16"
          fill="none"
          className={cn(
            "size-4",
            isProcessing
              ? "animate-horizon-status-working text-status-working"
              : "animate-horizon-status text-status-idle",
          )}
          aria-hidden="true"
        >
          <circle
            cx="8"
            cy="8"
            r={radius}
            fill="none"
            stroke={isProcessing ? "var(--status-working-track)" : "var(--status-idle-track)"}
            strokeWidth="2.5"
          />
          <circle
            cx="8"
            cy="8"
            r={radius}
            fill="none"
            stroke="currentColor"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={circumference * (1 - spinnerProgress / 100)}
            transform="rotate(-90 8 8)"
          />
        </svg>
      ) : status === "paused" ? (
        <CirclePauseIcon className="size-4" aria-hidden="true" />
      ) : status === "inactive" ? (
        <PowerIcon className="size-4" aria-hidden="true" />
      ) : status === "unavailable" ? (
        <TriangleAlertIcon className="size-4" aria-hidden="true" />
      ) : null}
    </div>
  );

  if (!showTooltip) {
    return indicator;
  }

  return (
    <TooltipProvider>
      <Tooltip>
        <TooltipTrigger render={indicator} />
        <TooltipContent side="right" sideOffset={8}>
          {tooltipLabel}
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
