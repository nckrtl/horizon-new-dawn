import { EllipsisIcon } from "lucide-react";
import type { ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

export function ActionMenuTrigger({
  available,
  label,
  working = false,
  className,
  children,
}: {
  available: boolean;
  label: string;
  working?: boolean;
  className?: string;
  children?: ReactNode;
}) {
  const content = children ?? <EllipsisIcon />;
  const button = (
    <Button
      type="button"
      variant="ghost"
      size="icon-sm"
      className={cn(
        className,
        !available &&
          !working &&
          "cursor-not-allowed opacity-50 hover:bg-transparent hover:text-current active:translate-y-0",
      )}
      aria-label={label}
      aria-disabled={!available && !working ? true : undefined}
      disabled={working}
    />
  );

  if (!available && !working) {
    return (
      <Tooltip>
        <TooltipTrigger render={button}>{content}</TooltipTrigger>
        <TooltipContent>No actions available</TooltipContent>
      </Tooltip>
    );
  }

  return <DropdownMenuTrigger render={button}>{content}</DropdownMenuTrigger>;
}
