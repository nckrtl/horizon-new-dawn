import { router, usePage } from "@inertiajs/react";
import { RefreshCwIcon } from "lucide-react";
import { useEffect, useState } from "react";

import { ThemeToggle } from "@/components/shell/theme-toggle";
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import { Switch } from "@/components/ui/switch";
import { cn } from "@/lib/utils";
import type { HorizonStatus as HorizonStatusValue } from "@/types/dashboard";

export function SidebarFooterControls({
  status,
  autoLoad,
  onAutoLoadChange,
}: {
  status: HorizonStatusValue;
  autoLoad: boolean;
  onAutoLoadChange: (enabled: boolean) => void;
}) {
  const page = usePage();
  const [activeRequestIds, setActiveRequestIds] = useState<Set<string>>(() => new Set());
  const hasUnresolvedDeferredProps = Object.values(page.deferredProps ?? {}).some((props) =>
    props.some((prop) => page.props[prop] === undefined),
  );
  const isLoading = autoLoad && (activeRequestIds.size > 0 || hasUnresolvedDeferredProps);

  useEffect(() => {
    const stopListeningForStarts = router.on("start", (event) => {
      if (event.detail.visit.prefetch) {
        return;
      }

      setActiveRequestIds((current) => new Set(current).add(event.detail.visit.id));
    });
    const stopListeningForFinishes = router.on("finish", (event) => {
      if (event.detail.visit.prefetch) {
        return;
      }

      setActiveRequestIds((current) => {
        const next = new Set(current);
        next.delete(event.detail.visit.id);

        return next;
      });
    });

    return () => {
      stopListeningForStarts();
      stopListeningForFinishes();
    };
  }, []);

  return (
    <SidebarGroup className="p-0" data-horizon-status={status}>
      <SidebarGroupLabel className="px-2">Settings</SidebarGroupLabel>
      <SidebarGroupContent>
        <SidebarMenu className="gap-1.5">
          <SidebarMenuItem>
            <ThemeToggle showTooltip={false} variant="menu" />
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton
              className="h-9 gap-2.5 rounded-lg px-2.5 pr-14"
              aria-label="Auto load new entries"
              aria-pressed={autoLoad}
              aria-busy={isLoading}
              onClick={() => onAutoLoadChange(!autoLoad)}
            >
              <RefreshCwIcon
                className={cn(
                  "text-muted-foreground",
                  isLoading && "animate-spin motion-reduce:animate-none",
                )}
                style={{ rotate: "0turn" }}
              />
              <span>Auto refresh</span>
            </SidebarMenuButton>
            <Switch
              checked={autoLoad}
              className="pointer-events-none absolute top-1/2 right-2.5 -translate-y-1/2"
              aria-hidden="true"
              tabIndex={-1}
            />
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  );
}

export { SidebarFooterControls as PageHeader };
