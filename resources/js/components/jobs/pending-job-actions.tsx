import { router } from "@inertiajs/react";
import { BanIcon, LoaderCircleIcon, PlayIcon } from "lucide-react";
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
import { destroy as cancelPendingJob } from "@/generated/routes/horizon-new-dawn/jobs/pending";
import { store as releaseDelayedJob } from "@/generated/routes/horizon-new-dawn/jobs/pending/release";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function PendingJobActionsMenu({
  jobId,
  horizonBaseUrl,
  canCancel = true,
  canRelease = false,
}: {
  jobId: string;
  horizonBaseUrl: string;
  canCancel?: boolean;
  canRelease?: boolean;
}) {
  const [action, setAction] = useState<"cancel" | "release" | null>(null);
  const [working, setWorking] = useState(false);

  const cancel = () => {
    const route = resolveHorizonRoute(cancelPendingJob(jobId), horizonBaseUrl);

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking(true),
      onSuccess: () => setAction(null),
      onFinish: () => setWorking(false),
    });
  };

  const release = () => {
    const route = resolveHorizonRoute(releaseDelayedJob(jobId), horizonBaseUrl);

    router.post(
      route.url,
      {},
      {
        preserveScroll: true,
        onStart: () => setWorking(true),
        onSuccess: () => setAction(null),
        onFinish: () => setWorking(false),
      },
    );
  };

  if (!canCancel && !canRelease) {
    return null;
  }

  const releasing = action === "release";

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger available label="Pending job actions" working={working} />
        <DropdownMenuContent align="end" className="w-44">
          {canRelease ? (
            <DropdownMenuItem onSelect={() => setAction("release")}>
              <PlayIcon />
              Make available now
            </DropdownMenuItem>
          ) : null}
          {canCancel ? (
            <DropdownMenuItem variant="destructive" onSelect={() => setAction("cancel")}>
              <BanIcon />
              Cancel job
            </DropdownMenuItem>
          ) : null}
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={action !== null} onOpenChange={(open) => !open && setAction(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {releasing ? "Make this job available now?" : "Cancel this job?"}
            </DialogTitle>
            <DialogDescription>
              {releasing
                ? "Move this job out of its schedule and make it available to workers immediately. Existing ready jobs remain ahead of it."
                : "Remove this job before a worker reserves it. The job will not run, and this action cannot be undone."}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              {releasing ? "Keep scheduled" : "Keep job"}
            </DialogClose>
            <Button
              type="button"
              variant={releasing ? "default" : "destructive"}
              disabled={working}
              data-test={releasing ? "confirm-release-job" : "confirm-cancel-job"}
              onClick={releasing ? release : cancel}
            >
              {working ? (
                <LoaderCircleIcon className="animate-spin" />
              ) : releasing ? (
                <PlayIcon />
              ) : (
                <BanIcon />
              )}
              {working
                ? releasing
                  ? "Making available…"
                  : "Cancelling…"
                : releasing
                  ? "Make available now"
                  : "Cancel job"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
