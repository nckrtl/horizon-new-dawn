import { router } from "@inertiajs/react";
import { EllipsisIcon, LoaderCircleIcon, Trash2Icon } from "lucide-react";
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
import { destroy as clearFinishedBatches } from "@/generated/routes/horizon-new-dawn/batches/clear-finished";
import { resolveHorizonRoute } from "@/lib/horizon-route";

export function BatchesActions({
  horizonBaseUrl,
  finishedBatches,
}: {
  horizonBaseUrl: string;
  finishedBatches: number;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState(false);

  const clear = () => {
    const route = resolveHorizonRoute(clearFinishedBatches(), horizonBaseUrl);

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
              aria-label="Batch actions"
              disabled={working}
            />
          }
        >
          <EllipsisIcon />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuItem
            variant="destructive"
            disabled={finishedBatches === 0}
            onSelect={() => setDialogOpen(true)}
          >
            <Trash2Icon />
            Clear finished batches
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear finished batches?</DialogTitle>
            <DialogDescription>
              Permanently remove {finishedBatches.toLocaleString()} finished or cancelled batch
              {finishedBatches === 1 ? "" : "es"}. Active batches are not affected. This action
              cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button type="button" variant="destructive" disabled={working} onClick={clear}>
              {working ? <LoaderCircleIcon className="animate-spin" /> : <Trash2Icon />}
              {working ? "Clearing…" : "Clear finished batches"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
