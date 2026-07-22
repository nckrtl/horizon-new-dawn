import type { ComponentProps } from "react";

import { cn } from "@/lib/utils";

export function CodeBlock({ className, ...props }: ComponentProps<"pre">) {
  return (
    <pre
      {...props}
      className={cn(
        "m-0 overflow-auto bg-code px-6 py-4 font-mono text-[12.5px] leading-6 font-medium whitespace-pre text-code-foreground",
        className,
      )}
      data-code-theme="dark"
    />
  );
}
