import { router } from "@inertiajs/react";
import { BanIcon } from "lucide-react";
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

const cancellationScopes: PendingCancellationScope[] = ["pending", "ready", "delayed"];

export function PendingJobsActions({
  horizonBaseUrl,
  disabled,
  queue,
  counts,
}: {
  horizonBaseUrl: string;
  disabled: boolean;
  queue?: string;
  counts: { ready: number | null; delayed: number | null };
}) {
  const [scope, setScope] = useState<PendingCancellationScope | null>(null);
  const [working, setWorking] = useState(false);
  const copy = scope ? cancellationCopy[scope] : null;

  const cancel = () => {
    if (!scope) {
      return;
    }

    const route = resolveHorizonRoute(
      cancelPendingJobs(scope, queue ? { query: { queue } } : undefined),
      horizonBaseUrl,
    );

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking(true),
      onSuccess: () => setScope(null),
      onFinish: () => setWorking(false),
    });
  };

  const availableScopes = cancellationScopes.filter((actionScope) => {
    if (counts.ready === null || counts.delayed === null) {
      return false;
    }

    return actionScope === "ready"
      ? counts.ready > 0
      : actionScope === "delayed"
        ? counts.delayed > 0
        : counts.ready > 0 && counts.delayed > 0;
  });

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger
          available={availableScopes.length > 0 && !disabled}
          label={queue ? `Pending jobs actions for ${queue}` : "Pending jobs actions"}
          working={working}
        />
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuGroup>
            {availableScopes.map((actionScope) => {
              const actionCopy = cancellationCopy[actionScope];

              return (
                <DropdownMenuItem
                  key={actionScope}
                  variant="destructive"
                  disabled={disabled}
                  onSelect={() => setScope(actionScope)}
                >
                  <BanIcon />
                  {actionCopy.label}
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={scope !== null} onOpenChange={(open) => !open && setScope(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {copy && queue ? copy.title.replace(/\?$/, ` from ${queue}?`) : copy?.title}
            </DialogTitle>
            <DialogDescription>
              {copy?.description}
              {queue ? ` This action is limited to the ${queue} queue.` : null}
            </DialogDescription>
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
