import { router } from "@inertiajs/react";
import { LoaderCircleIcon, RotateCcwIcon } from "lucide-react";
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem } from "@/components/ui/dropdown-menu";
import { store as retryQueueBatches } from "@/generated/routes/horizon-new-dawn/queues/batches/retry-failed";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function QueueBatchesActions({
  queue,
  failedJobs,
  horizonBaseUrl,
}: {
  queue: string;
  failedJobs: number;
  horizonBaseUrl: string;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const retry = () => {
    const route = resolveHorizonRoute(retryQueueBatches(encodeURIComponent(queue)), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onSuccess: () => setDialogOpen(false),
        onFinish: () => setWorking(false),
      },
    );
  };

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger
          available={failedJobs > 0}
          label={`Batch jobs actions for ${queue}`}
          working={working}
        />
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuItem disabled={failedJobs === 0} onSelect={() => setDialogOpen(true)}>
            <RotateCcwIcon className="size-3.5" />
            Retry all failed jobs
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Retry all failed jobs from these batches?</DialogTitle>
            <DialogDescription>
              Schedule every eligible failed job from the retained batches in this queue. Previously
              retried jobs are skipped.
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
    </>
  );
}
