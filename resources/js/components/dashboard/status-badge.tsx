import { Badge } from "@/components/ui/badge";
import type { HorizonStatus } from "@/types/dashboard";

const statusDetails = {
  running: {
    label: "Running",
    variant: "success" as const,
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
} satisfies Record<HorizonStatus, object>;

export function StatusBadge({ status }: { status: HorizonStatus }) {
  const details = statusDetails[status];

  return (
    <Badge variant={details.variant} data-status={status}>
      {details.label}
    </Badge>
  );
}
