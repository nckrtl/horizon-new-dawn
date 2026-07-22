import { router } from "@inertiajs/react";
import { CircleXIcon, LoaderCircleIcon, RotateCcwIcon, Trash2Icon } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { ActionMenuTrigger } from "@/components/ui/action-menu-trigger";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import {
  destroy as stopMonitoring,
  index as monitoringIndex,
} from "@/generated/routes/horizon-new-dawn/monitoring";
import { destroy as clearRecentJobs } from "@/generated/routes/horizon-new-dawn/monitoring/jobs";
import { store as retryFailedJobs } from "@/generated/routes/horizon-new-dawn/monitoring/retry-failed";
import { resolveHorizonRoute } from "@/lib/horizon-route";

type MonitoringAction = "clear" | "retry" | "stop";

export function MonitoringActionsMenu({
  tag,
  horizonBaseUrl,
  trackedCount,
  failedCount,
  redirectToIndex = false,
}: {
  tag: string;
  horizonBaseUrl: string;
  trackedCount?: number;
  failedCount?: number;
  redirectToIndex?: boolean;
}) {
  const [clearDialogOpen, setClearDialogOpen] = useState(false);
  const [retryDialogOpen, setRetryDialogOpen] = useState(false);
  const [working, setWorking] = useState<MonitoringAction | null>(null);

  const stop = () => {
    const route = resolveHorizonRoute(stopMonitoring(encodeURIComponent(tag)), horizonBaseUrl);
    const indexUrl = resolveHorizonRoute(monitoringIndex(), horizonBaseUrl).url;

    router.delete(route.url, {
      preserveScroll: !redirectToIndex,
      onStart: () => setWorking("stop"),
      onSuccess: () => {
        if (redirectToIndex) {
          router.visit(indexUrl);
        }
      },
      onFinish: () => setWorking(null),
    });
  };

  const retry = () => {
    const route = resolveHorizonRoute(retryFailedJobs(encodeURIComponent(tag)), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking("retry"),
        onSuccess: () => setRetryDialogOpen(false),
        onFinish: () => setWorking(null),
      },
    );
  };

  const clear = () => {
    const route = resolveHorizonRoute(clearRecentJobs(encodeURIComponent(tag)), horizonBaseUrl);

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking("clear"),
      onSuccess: () => setClearDialogOpen(false),
      onFinish: () => setWorking(null),
    });
  };

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger
          available
          label={working ? `Updating monitoring for ${tag}` : `Monitoring actions for ${tag}`}
          working={working !== null}
        >
          {working ? <LoaderCircleIcon className="animate-spin" /> : undefined}
        </ActionMenuTrigger>
        <DropdownMenuContent align="end" className="w-48">
          {(failedCount ?? 0) > 0 || (trackedCount ?? 0) > 0 ? (
            <>
              <DropdownMenuGroup>
                {(failedCount ?? 0) > 0 ? (
                  <DropdownMenuItem onSelect={() => setRetryDialogOpen(true)}>
                    <RotateCcwIcon />
                    Retry failed jobs
                  </DropdownMenuItem>
                ) : null}
                {(trackedCount ?? 0) > 0 ? (
                  <DropdownMenuItem onSelect={() => setClearDialogOpen(true)}>
                    <Trash2Icon />
                    Clear recent jobs
                  </DropdownMenuItem>
                ) : null}
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
            </>
          ) : null}
          <DropdownMenuGroup>
            <DropdownMenuItem variant="destructive" onSelect={stop}>
              <CircleXIcon />
              Stop monitoring
            </DropdownMenuItem>
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={retryDialogOpen} onOpenChange={setRetryDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Retry failed jobs tagged {tag}?</DialogTitle>
            <DialogDescription>
              Schedule {failedCount} eligible failed {failedCount === 1 ? "job" : "jobs"} for retry.
              Previously retried jobs are skipped.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose
              render={<Button type="button" variant="ghost" disabled={working !== null} />}
            >
              Cancel
            </DialogClose>
            <Button type="button" disabled={working !== null} onClick={retry}>
              {working === "retry" ? (
                <LoaderCircleIcon data-icon="inline-start" className="animate-spin" />
              ) : null}
              {working === "retry" ? "Retrying…" : "Retry failed jobs"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={clearDialogOpen} onOpenChange={setClearDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear recent jobs for {tag}?</DialogTitle>
            <DialogDescription>
              Remove {trackedCount} recent {trackedCount === 1 ? "job" : "jobs"} from this monitor.
              Monitoring continues and new matching jobs will appear.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose
              render={<Button type="button" variant="ghost" disabled={working !== null} />}
            >
              Cancel
            </DialogClose>
            <Button type="button" variant="destructive" disabled={working !== null} onClick={clear}>
              {working === "clear" ? (
                <LoaderCircleIcon data-icon="inline-start" className="animate-spin" />
              ) : null}
              {working === "clear" ? "Clearing…" : "Clear recent jobs"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
