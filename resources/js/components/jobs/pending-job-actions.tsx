import { router } from "@inertiajs/react";
import { BanIcon, EllipsisIcon, LoaderCircleIcon } from "lucide-react";
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
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { destroy as cancelPendingJob } from "@/generated/routes/horizon-new-dawn/jobs/pending";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function PendingJobActionsMenu({
  jobId,
  horizonBaseUrl,
}: {
  jobId: string;
  horizonBaseUrl: string;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const cancel = () => {
    const route = resolveHorizonRoute(cancelPendingJob(jobId), horizonBaseUrl);

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
              aria-label="Pending job actions"
              disabled={working}
            />
          }
        >
          <EllipsisIcon />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-44">
          <DropdownMenuItem variant="destructive" onSelect={() => setDialogOpen(true)}>
            <BanIcon />
            Cancel job
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel this job?</DialogTitle>
            <DialogDescription>
              Remove this job before a worker reserves it. The job will not run, and this action
              cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Keep job
            </DialogClose>
            <Button
              type="button"
              variant="destructive"
              disabled={working}
              data-test="confirm-cancel-job"
              onClick={cancel}
            >
              {working ? <LoaderCircleIcon className="animate-spin" /> : <BanIcon />}
              {working ? "Cancelling…" : "Cancel job"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
