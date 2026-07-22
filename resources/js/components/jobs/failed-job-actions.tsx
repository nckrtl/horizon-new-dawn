import { router } from "@inertiajs/react";
import { EllipsisIcon, LoaderCircleIcon, RotateCcwIcon, Trash2Icon } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
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
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { destroy as removeFailedJob } from "@/generated/routes/horizon-new-dawn/failed-jobs";
import { destroy as clearAllFailedJobs } from "@/generated/routes/horizon-new-dawn/failed-jobs/clear-all";
import { store as retryAllFailedJobs } from "@/generated/routes/horizon-new-dawn/failed-jobs/retry-all";
import { store as retryFailedJob } from "@/generated/routes/horizon-new-dawn/failed-jobs/retry";
import { cn } from "@/lib/utils";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function RetryFailedJobButton({
  jobId,
  horizonBaseUrl,
  disabled = false,
  showLabel = false,
}: {
  jobId: string;
  horizonBaseUrl: string;
  disabled?: boolean;
  showLabel?: boolean;
}) {
  const [working, setWorking] = useState(false);

  const retry = () => {
    const route = resolveHorizonRoute(retryFailedJob(jobId), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onFinish: () => setWorking(false),
      },
    );
  };

  return (
    <Button
      type="button"
      size={showLabel ? "default" : "icon-sm"}
      variant={showLabel ? "default" : "ghost"}
      className={cn(
        !showLabel && "text-action-retry hover:bg-action-retry/15! hover:text-action-retry!",
      )}
      aria-label={working ? "Retrying failed job" : "Retry failed job"}
      disabled={disabled || working}
      onClick={retry}
    >
      {working ? <LoaderCircleIcon className="animate-spin" /> : <RotateCcwIcon />}
      {showLabel ? <span>{working ? "Retrying" : "Retry"}</span> : null}
    </Button>
  );
}

export function RetryAllFailedJobsButton({
  horizonBaseUrl,
  disabled,
}: {
  horizonBaseUrl: string;
  disabled: boolean;
}) {
  const [working, setWorking] = useState(false);

  const retry = () => {
    const route = resolveHorizonRoute(retryAllFailedJobs(), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onFinish: () => setWorking(false),
      },
    );
  };

  return (
    <Button
      type="button"
      size="icon"
      className="sm:h-[34px] sm:w-auto sm:gap-2 sm:px-2.5"
      aria-label={working ? "Retrying failed jobs" : "Retry all failed jobs"}
      disabled={disabled || working}
      onClick={retry}
    >
      {working ? <LoaderCircleIcon className="animate-spin" /> : <RotateCcwIcon />}
      <span className="hidden sm:inline">{working ? "Retrying" : "Retry All"}</span>
    </Button>
  );
}

export function FailedJobsActionsMenu({
  horizonBaseUrl,
  disabled,
}: {
  horizonBaseUrl: string;
  disabled: boolean;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const retryAll = () => {
    const route = resolveHorizonRoute(retryAllFailedJobs(), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onFinish: () => setWorking(false),
      },
    );
  };

  const clear = () => {
    const route = resolveHorizonRoute(clearAllFailedJobs(), horizonBaseUrl);

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking(true),
      onSuccess: () => setDialogOpen(false),
      onFinish: () => setWorking(false),
    });
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label="Failed jobs actions"
              disabled={working}
            />
          }
        >
          <EllipsisIcon />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-44">
          <DropdownMenuGroup>
            <DropdownMenuItem disabled={disabled} onSelect={retryAll}>
              <RotateCcwIcon className="size-3.5" />
              Retry all
            </DropdownMenuItem>
          </DropdownMenuGroup>
          <DropdownMenuSeparator />
          <DropdownMenuGroup>
            <DropdownMenuItem
              variant="destructive"
              disabled={disabled}
              onSelect={() => setDialogOpen(true)}
            >
              <Trash2Icon />
              Clear all failed jobs
            </DropdownMenuItem>
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear all failed jobs?</DialogTitle>
            <DialogDescription>
              Permanently remove every failed job record from Horizon and Laravel’s failed-job
              storage. These jobs will no longer be available to inspect or retry. This action
              cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button type="button" variant="destructive" disabled={working} onClick={clear}>
              {working ? <LoaderCircleIcon className="animate-spin" /> : <Trash2Icon />}
              {working ? "Clearing…" : "Clear all failed jobs"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

export function FailedJobActionsMenu({
  jobId,
  horizonBaseUrl,
  canRetry,
}: {
  jobId: string;
  horizonBaseUrl: string;
  canRetry: boolean;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const retry = () => {
    const route = resolveHorizonRoute(retryFailedJob(jobId), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onFinish: () => setWorking(false),
      },
    );
  };

  const remove = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const route = resolveHorizonRoute(removeFailedJob(jobId), horizonBaseUrl);

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking(true),
      onSuccess: () => setDialogOpen(false),
      onFinish: () => setWorking(false),
    });
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label={canRetry ? "Retry failed job" : "Remove failed job"}
              disabled={working}
            />
          }
        >
          <EllipsisIcon />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-44">
          {canRetry ? (
            <>
              <DropdownMenuItem onSelect={retry}>
                <RotateCcwIcon className="size-3.5" />
                Retry
              </DropdownMenuItem>
              <DropdownMenuSeparator />
            </>
          ) : null}
          <DropdownMenuItem variant="destructive" onSelect={() => setDialogOpen(true)}>
            <Trash2Icon />
            Remove
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <form onSubmit={remove}>
            <DialogHeader>
              <DialogTitle>Remove failed job?</DialogTitle>
              <DialogDescription>
                This permanently removes the failed job record. This action cannot be undone.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter className="mt-5">
              <DialogClose render={<Button type="button" variant="outline" disabled={working} />}>
                Cancel
              </DialogClose>
              <Button type="submit" variant="destructive" disabled={working}>
                {working ? <LoaderCircleIcon className="animate-spin" /> : <Trash2Icon />}
                {working ? "Removing" : "Remove"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}
