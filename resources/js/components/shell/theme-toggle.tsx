import { MonitorIcon, MoonIcon, SunIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { SidebarMenuButton } from "@/components/ui/sidebar";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { useColorScheme, type ColorScheme } from "@/hooks/use-color-scheme";

const schemeDetails = {
  system: { icon: MonitorIcon, next: "dark" },
  dark: { icon: MoonIcon, next: "light" },
  light: { icon: SunIcon, next: "system" },
} satisfies Record<ColorScheme, { icon: typeof MonitorIcon; next: ColorScheme }>;

export function ThemeToggle({
  showTooltip = true,
  variant = "button",
}: {
  showTooltip?: boolean;
  variant?: "button" | "menu";
}) {
  const { scheme, cycle } = useColorScheme();
  const details = schemeDetails[scheme];
  const Icon = details.icon;
  const label = `Color scheme: ${scheme}. Switch to ${details.next}.`;

  const button =
    variant === "menu" ? (
      <SidebarMenuButton
        className="h-9 gap-2.5 rounded-lg px-2.5"
        aria-label={label}
        onClick={cycle}
      >
        <Icon className="text-muted-foreground" aria-hidden="true" />
        <span>Appearance</span>
      </SidebarMenuButton>
    ) : (
      <Button
        variant="outline"
        size="icon-lg"
        className="rounded-[10px] border-0 border-t border-border bg-card text-foreground shadow-sm hover:bg-accent dark:bg-card"
        aria-label={label}
        onClick={cycle}
      >
        <Icon aria-hidden="true" />
      </Button>
    );

  if (!showTooltip || variant === "menu") {
    return button;
  }

  return (
    <Tooltip>
      <TooltipTrigger render={button} />
      <TooltipContent side="right">{label}</TooltipContent>
    </Tooltip>
  );
}
