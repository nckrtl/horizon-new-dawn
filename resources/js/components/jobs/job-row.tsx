import { Link, router } from "@inertiajs/react";
import type { ReactNode } from "react";

import { JobTags } from "@/components/jobs/job-tags";
import { RetryMarker } from "@/components/jobs/retry-marker";
import { TableCell, TableRow } from "@/components/ui/table";
import { isInteractiveTarget } from "@/lib/interactive-target";

export type JobRowView = {
  id: string;
  name: string;
  queue: string;
  tags: readonly string[];
  runtime: string;
  timestamp: string;
  retried?: boolean;
};

export function JobRow({
  job,
  detailUrl,
  actions,
}: {
  job: JobRowView;
  detailUrl: string;
  actions?: ReactNode;
}) {
  return (
    <TableRow
      className="cursor-pointer"
      onClick={(event) => {
        if (isInteractiveTarget(event.target)) {
          return;
        }

        router.visit(detailUrl);
      }}
      onMouseEnter={() => router.prefetch(detailUrl)}
    >
      <TableCell className="max-w-80 whitespace-normal">
        <Link className="font-normal text-foreground" href={detailUrl} prefetch>
          {job.name}
        </Link>
        <p className="mt-0.5 truncate font-mono text-xs text-muted-foreground" title={job.id}>
          {job.id}
        </p>
      </TableCell>
      <TableCell>{job.queue}</TableCell>
      <TableCell>
        <JobTags tags={job.tags} />
      </TableCell>
      <TableCell className="font-mono text-xs tabular-nums">{job.runtime}</TableCell>
      <TableCell className="text-muted-foreground">{job.timestamp}</TableCell>
      {job.retried || actions ? (
        <TableCell className="text-right">
          <div className="flex justify-end gap-2">
            {job.retried ? <RetryMarker /> : null}
            {actions}
          </div>
        </TableCell>
      ) : null}
    </TableRow>
  );
}
