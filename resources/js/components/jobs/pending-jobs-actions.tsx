import { router } from "@inertiajs/react";
import { BanIcon, EllipsisIcon } from "lucide-react";
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
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Spinner } from "@/components/ui/spinner";
import { destroy as cancelPendingJobs } from "@/generated/routes/horizon-new-dawn/jobs/pending/cancel";
import { resolveHorizonRoute } from "@/lib/horizon-route";

type PendingCancellationScope = "ready" | "delayed" | "pending";

const cancellationCopy: Record<
  PendingCancellationScope,
  { label: string; title: string; description: string }
> = {
  ready: {
    label: "Cancel all ready jobs",
    title: "Cancel all ready jobs?",
    description:
      "Cancel every ready job before a worker reserves it. Delayed, reserved, and running jobs will not be affected. Batched jobs are skipped and must be cancelled from their batch.",
  },
  delayed: {
    label: "Cancel all delayed jobs",
    title: "Cancel all delayed jobs?",
    description:
      "Cancel every job that is currently delayed. Ready, reserved, and running jobs will not be affected. Batched jobs are skipped and must be cancelled from their batch.",
  },
  pending: {
    label: "Cancel all pending jobs",
    title: "Cancel all pending jobs?",
    description:
      "Cancel every ready and delayed job before a worker reserves it. Reserved and running jobs will not be affected. Batched jobs are skipped and must be cancelled from their batch.",
  },
};

export function PendingJobsActions({
  horizonBaseUrl,
  disabled,
}: {
  horizonBaseUrl: string;
  disabled: boolean;
}) {
  const [scope, setScope] = useState<PendingCancellationScope | null>(null);
  const [working, setWorking] = useState(false);
  const copy = scope ? cancellationCopy[scope] : null;

  const cancel = () => {
    if (!scope) {
      return;
    }

    const route = resolveHorizonRoute(cancelPendingJobs(scope), horizonBaseUrl);

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking(true),
      onSuccess: () => setScope(null),
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
              aria-label="Pending jobs actions"
              disabled={working}
            />
          }
        >
          <EllipsisIcon />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuGroup>
            {(Object.entries(cancellationCopy) as [PendingCancellationScope, typeof copy][]).map(
              ([actionScope, actionCopy]) => (
                <DropdownMenuItem
                  key={actionScope}
                  variant="destructive"
                  disabled={disabled}
                  onSelect={() => setScope(actionScope)}
                >
                  <BanIcon />
                  {actionCopy?.label}
                </DropdownMenuItem>
              ),
            )}
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={scope !== null} onOpenChange={(open) => !open && setScope(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{copy?.title}</DialogTitle>
            <DialogDescription>{copy?.description}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button
              type="button"
              variant="destructive"
              disabled={working}
              data-test="confirm-cancel-pending-jobs"
              onClick={cancel}
            >
              {working ? (
                <Spinner data-icon="inline-start" />
              ) : (
                <BanIcon data-icon="inline-start" />
              )}
              {working ? "Cancelling…" : copy?.label}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
