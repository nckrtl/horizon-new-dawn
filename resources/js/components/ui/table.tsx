import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@/lib/utils";

function Table({ className, ...props }: React.ComponentProps<"table">) {
  return (
    <div
      data-slot="table-container"
      className="relative w-full overflow-x-auto min-[1140px]:overflow-x-visible"
    >
      <table
        data-slot="table"
        className={cn("w-full caption-bottom border-collapse text-sm", className)}
        {...props}
      />
    </div>
  );
}

function TableHeader({ className, ...props }: React.ComponentProps<"thead">) {
  return (
    <thead
      data-slot="table-header"
      className={cn(
        "sticky top-0 z-10 [&_tr]:border-0 [&_th]:shadow-[inset_0_-1px_0_var(--separator)]",
        className,
      )}
      {...props}
    />
  );
}

function TableBody({ className, ...props }: React.ComponentProps<"tbody">) {
  return (
    <tbody
      data-slot="table-body"
      className={cn("[&_tr:last-child]:border-0", className)}
      {...props}
    />
  );
}

function TableFooter({ className, ...props }: React.ComponentProps<"tfoot">) {
  return (
    <tfoot
      data-slot="table-footer"
      className={cn("border-t bg-muted/50 font-medium [&>tr]:last:border-b-0", className)}
      {...props}
    />
  );
}

function TableRow({ className, onClick, ...props }: React.ComponentProps<"tr">) {
  return (
    <tr
      data-slot="table-row"
      data-clickable-row={onClick ? "" : undefined}
      className={cn(
        "border-b border-separator transition-colors has-aria-expanded:bg-table-row-hover data-[state=selected]:bg-muted",
        onClick && "cursor-pointer hover:bg-table-row-hover",
        className,
      )}
      onClick={onClick}
      {...props}
    />
  );
}

function TableHead({ className, ...props }: React.ComponentProps<"th">) {
  return (
    <th
      data-slot="table-head"
      className={cn(
        "h-10 bg-th px-6 text-left align-middle text-[13px] font-medium whitespace-nowrap text-muted-foreground [&:has([role=checkbox])]:pr-0",
        className,
      )}
      {...props}
    />
  );
}

const tableCellVariants = cva(
  "px-6 py-3 align-top whitespace-nowrap min-[1140px]:align-middle [&:has([role=checkbox])]:pr-0",
  {
    variants: {
      tone: {
        primary: "text-foreground",
        secondary: "text-muted-foreground",
      },
    },
    defaultVariants: {
      tone: "primary",
    },
  },
);

function TableCell({
  className,
  tone = "primary",
  ...props
}: React.ComponentProps<"td"> & VariantProps<typeof tableCellVariants>) {
  return (
    <td
      data-slot="table-cell"
      data-tone={tone}
      className={cn(tableCellVariants({ tone }), className)}
      {...props}
    />
  );
}

function TableCaption({ className, ...props }: React.ComponentProps<"caption">) {
  return (
    <caption
      data-slot="table-caption"
      className={cn("mt-4 text-sm text-muted-foreground", className)}
      {...props}
    />
  );
}

export {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableHead,
  TableRow,
  TableCell,
  TableCaption,
  tableCellVariants,
};
