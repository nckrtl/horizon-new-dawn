import { mergeProps } from "@base-ui/react/merge-props";
import { useRender } from "@base-ui/react/use-render";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@/lib/utils";

const badgeVariants = cva(
  "group/badge inline-flex h-5 w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-4xl border border-transparent px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-all focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none [&>svg]:size-3!",
  {
    variants: {
      variant: {
        default: "bg-primary/10 text-primary [a]:hover:bg-primary/20",
        secondary: "bg-secondary text-secondary-foreground [a]:hover:bg-secondary/80",
        destructive:
          "bg-status-unavailable text-status-unavailable-foreground focus-visible:ring-status-unavailable-foreground/20 [a]:hover:bg-status-unavailable/80",
        delayed:
          "bg-status-delayed text-status-delayed-foreground focus-visible:ring-status-delayed-foreground/20 [a]:hover:bg-status-delayed/80",
        processing:
          "bg-status-processing text-status-processing-foreground focus-visible:ring-status-processing-foreground/20 [a]:hover:bg-status-processing/80",
        retry:
          "bg-status-retry text-status-retry-foreground focus-visible:ring-status-retry-foreground/20 [a]:hover:bg-status-retry/80",
        success:
          "bg-status-running text-status-running-foreground focus-visible:ring-status-running-foreground/20 [a]:hover:bg-status-running/80",
        warning:
          "bg-status-paused text-status-paused-foreground focus-visible:ring-status-paused-foreground/20 [a]:hover:bg-status-paused/80",
        outline: "border-border text-foreground [a]:hover:bg-muted [a]:hover:text-muted-foreground",
        ghost: "hover:bg-muted hover:text-muted-foreground dark:hover:bg-muted/50",
        link: "text-primary underline-offset-4 hover:underline",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);

function Badge({
  className,
  variant = "default",
  render,
  ...props
}: useRender.ComponentProps<"span"> & VariantProps<typeof badgeVariants>) {
  return useRender({
    defaultTagName: "span",
    props: mergeProps<"span">(
      {
        className: cn(badgeVariants({ variant }), className),
      },
      props,
    ),
    render,
    state: {
      slot: "badge",
      variant,
    },
  });
}

export { Badge, badgeVariants };
