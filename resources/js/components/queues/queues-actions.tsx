import { router } from "@inertiajs/react";
import { LoaderCircleIcon, Trash2Icon } from "lucide-react";
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
import { destroy as clearAllQueues } from "@/generated/routes/horizon-new-dawn/queues/clear-all";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function QueuesActions({
  horizonBaseUrl,
  queueCount,
  pendingJobs,
}: {
  horizonBaseUrl: string;
  queueCount: number;
  pendingJobs: number;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const clear = () => {
    const route = resolveHorizonRoute(clearAllQueues(), horizonBaseUrl);

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
          available={pendingJobs > 0}
          label="Queue list actions"
          working={working}
        />
        <DropdownMenuContent align="end" className="w-44">
          <DropdownMenuItem
            variant="destructive"
            disabled={pendingJobs === 0}
            onSelect={() => setDialogOpen(true)}
          >
            <Trash2Icon />
            Clear all queues
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear all queues?</DialogTitle>
            <DialogDescription>
              Permanently delete all pending, delayed, and reserved jobs from {queueCount}{" "}
              supervised
              {queueCount === 1 ? " queue" : " queues"}. Jobs already processing may still finish.
              This cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button type="button" variant="destructive" disabled={working} onClick={clear}>
              {working ? <LoaderCircleIcon className="animate-spin" /> : <Trash2Icon />}
              {working ? "Clearing…" : "Clear all queues"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
