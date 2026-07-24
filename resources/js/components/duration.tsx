import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { formatDuration, formatRawDuration, type DurationFormat } from "@/lib/format-duration";
import { cn } from "@/lib/utils";

export function Duration({
  seconds,
  format = "approximate",
  showRawValue = false,
  className,
}: {
  seconds: number;
  format?: DurationFormat;
  showRawValue?: boolean;
  className?: string;
}) {
  const value = formatDuration(seconds, format);

  if (!showRawValue) {
    return <span className={cn("tabular-nums", className)}>{value}</span>;
  }

  return (
    <Tooltip>
      <TooltipTrigger
        render={
          <span
            className={cn(
              "inline-flex cursor-help rounded-sm tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
              className,
            )}
            tabIndex={0}
          />
        }
      >
        {value}
      </TooltipTrigger>
      <TooltipContent>Raw value: {formatRawDuration(seconds)}</TooltipContent>
    </Tooltip>
  );
}
