import { Badge } from "@/components/ui/badge";
import type { HorizonDisplayStatus } from "@/types/dashboard";

const statusDetails = {
  running: {
    label: "Running",
    variant: "success" as const,
  },
  continuing: {
    label: "Continuing",
    variant: "processing" as const,
  },
  pausing: {
    label: "Pausing",
    variant: "warning" as const,
  },
  paused: {
    label: "Paused",
    variant: "warning" as const,
  },
  inactive: {
    label: "Inactive",
    variant: "outline" as const,
  },
  unavailable: {
    label: "Unavailable",
    variant: "destructive" as const,
  },
} satisfies Record<HorizonDisplayStatus, object>;

export function StatusBadge({ status }: { status: HorizonDisplayStatus }) {
  const details = statusDetails[status];

  return (
    <Badge variant={details.variant} data-status={status}>
      {details.label}
    </Badge>
  );
}
