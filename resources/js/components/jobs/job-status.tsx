import {
  BellOffIcon,
  CircleCheckIcon,
  CirclePauseIcon,
  CircleXIcon,
  Clock3Icon,
  LoaderCircleIcon,
  type LucideIcon,
} from "lucide-react";

import { cn } from "@/lib/utils";

export type JobStatusValue = "ready" | "reserved" | "delayed" | "completed" | "silenced" | "failed";

const statuses = {
  ready: {
    label: "Ready",
    icon: CirclePauseIcon,
    iconClassName: "text-muted-foreground",
  },
  reserved: {
    label: "Reserved",
    icon: LoaderCircleIcon,
    iconClassName: "text-status-processing-icon",
  },
  delayed: {
    label: "Delayed",
    icon: Clock3Icon,
    iconClassName: "text-status-warning-icon",
  },
  completed: {
    label: "Completed",
    icon: CircleCheckIcon,
    iconClassName: "text-status-success-icon",
  },
  silenced: {
    label: "Silenced",
    icon: BellOffIcon,
    iconClassName: "text-muted-foreground",
  },
  failed: {
    label: "Failed",
    icon: CircleXIcon,
    iconClassName: "text-status-destructive-icon",
  },
} satisfies Record<JobStatusValue, { label: string; icon: LucideIcon; iconClassName: string }>;

export function JobStatus({ status }: { status: JobStatusValue }) {
  const statusDetails = statuses[status];
  const StatusIcon = statusDetails.icon;

  return (
    <div
      className="inline-flex items-center gap-1.5"
      role="status"
      aria-label={`Job status: ${statusDetails.label}`}
    >
      <StatusIcon aria-hidden="true" className={cn("size-4", statusDetails.iconClassName)} />
      <span>{statusDetails.label}</span>
    </div>
  );
}
