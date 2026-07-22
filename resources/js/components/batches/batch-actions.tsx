import { router } from "@inertiajs/react";
import { BanIcon, LoaderCircleIcon, RotateCcwIcon, Trash2Icon } from "lucide-react";
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
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import { store as cancelBatch } from "@/generated/routes/horizon-new-dawn/batches/cancel";
import { destroy as clearBatchFailedJobs } from "@/generated/routes/horizon-new-dawn/batches/failed/clear";
import { store as retryBatch } from "@/generated/routes/horizon-new-dawn/batches/retry";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function BatchPendingJobsActions({
  batchId,
  horizonBaseUrl,
  canCancel,
  canRetry = false,
  ariaLabel = "Pending batch jobs actions",
}: {
  batchId: string;
  horizonBaseUrl: string;
  canCancel: boolean;
  canRetry?: boolean;
  ariaLabel?: string;
}) {
  const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
  const [retryDialogOpen, setRetryDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const retry = () => {
    const route = resolveHorizonRoute(retryBatch(batchId), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onSuccess: () => setRetryDialogOpen(false),
        onFinish: () => setWorking(false),
      },
    );
  };

  const cancel = () => {
    const route = resolveHorizonRoute(cancelBatch(batchId), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onSuccess: () => setCancelDialogOpen(false),
        onFinish: () => setWorking(false),
      },
    );
  };

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger available={canRetry || canCancel} label={ariaLabel} working={working} />
        <DropdownMenuContent align="end" className="w-52">
          {canRetry ? (
            <>
              <DropdownMenuItem onSelect={() => setRetryDialogOpen(true)}>
                <RotateCcwIcon className="size-3.5" />
                Retry all failed jobs
              </DropdownMenuItem>
              <DropdownMenuSeparator />
            </>
          ) : null}
          <DropdownMenuItem
            variant="destructive"
            disabled={!canCancel}
            onSelect={() => setCancelDialogOpen(true)}
          >
            <BanIcon />
            Cancel batch
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={retryDialogOpen} onOpenChange={setRetryDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Retry all failed jobs?</DialogTitle>
            <DialogDescription>
              Retry every failed job retained for this batch. Jobs that fail again will return to
              the failed jobs list.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button type="button" disabled={working} onClick={retry}>
              {working ? <LoaderCircleIcon className="animate-spin" /> : <RotateCcwIcon />}
              {working ? "Retrying…" : "Retry all failed jobs"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel this batch?</DialogTitle>
            <DialogDescription>
              Mark this batch as cancelled. Pending jobs that honor Laravel batch cancellation will
              be skipped, while jobs already running may still finish. This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Keep batch running
            </DialogClose>
            <Button type="button" variant="destructive" disabled={working} onClick={cancel}>
              {working ? <LoaderCircleIcon className="animate-spin" /> : <BanIcon />}
              {working ? "Cancelling…" : "Cancel batch"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

export function BatchFailedJobsActions({
  batchId,
  horizonBaseUrl,
  disabled,
}: {
  batchId: string;
  horizonBaseUrl: string;
  disabled: boolean;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const retry = () => {
    const route = resolveHorizonRoute(retryBatch(batchId), horizonBaseUrl);

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
    const route = resolveHorizonRoute(clearBatchFailedJobs(batchId), horizonBaseUrl);

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
        <ActionMenuTrigger
          available={!disabled}
          label="Failed batch jobs actions"
          working={working}
        />
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuItem onSelect={retry}>
            <RotateCcwIcon className="size-3.5" />
            Retry all failed jobs
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem variant="destructive" onSelect={() => setDialogOpen(true)}>
            <Trash2Icon />
            Clear all failed jobs
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear this batch’s failed jobs?</DialogTitle>
            <DialogDescription>
              Permanently remove every failed job record retained for this batch. Failed jobs from
              other batches are not affected. This action cannot be undone.
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
