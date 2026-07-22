import { Link } from "@inertiajs/react";
import type { ReactNode } from "react";

import { JobTags } from "@/components/jobs/job-tags";
import { TableCell } from "@/components/ui/table";
import { cn } from "@/lib/utils";

export function JobTablePrimaryCell({
  name,
  fullName = name,
  queue,
  tags,
  href,
  accessory,
  details,
  tagLimit,
  className,
}: {
  name: string;
  fullName?: string;
  queue: string;
  tags: readonly string[];
  href: string;
  accessory?: ReactNode;
  details?: ReactNode;
  tagLimit?: number;
  className?: string;
}) {
  return (
    <TableCell className={cn("max-w-xl px-6 whitespace-normal", className)}>
      <div className="flex items-center gap-2">
        <Link
          className="min-w-0 truncate font-normal text-foreground"
          href={href}
          prefetch
          title={fullName}
        >
          {name}
        </Link>
        {accessory}
      </div>
      <div className="mt-1 flex min-h-5 flex-wrap items-center gap-1.5 text-[12.5px] text-muted-foreground">
        <span>Queue: {queue}</span>
        {details}
        {tags.length > 0 ? (
          <JobTags
            tags={tags}
            limit={tagLimit}
            className="gap-1.5"
            badgeClassName="h-auto px-2 py-px text-[11px] leading-4"
          />
        ) : null}
      </div>
    </TableCell>
  );
}
