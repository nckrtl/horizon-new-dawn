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
import { destroy as clearBatches } from "@/generated/routes/horizon-new-dawn/batches/clear";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { BatchClearCounts, BatchClearScope } from "@/types/batches";

const clearScopes: BatchClearScope[] = ["incomplete", "complete", "finished", "cancelled"];

const clearCopy: Record<
  BatchClearScope,
  { label: string; title: string; description: (count: number) => string }
> = {
  incomplete: {
    label: "Clear incomplete batches",
    title: "Clear incomplete batches?",
    description: (count) =>
      `Permanently remove ${count.toLocaleString()} failure-stalled ${count === 1 ? "batch" : "batches"} with no pending or reserved jobs. In-progress and cancelled batches are not affected. This action cannot be undone.`,
  },
  complete: {
    label: "Clear complete batches",
    title: "Clear complete batches?",
    description: (count) =>
      `Permanently remove ${count.toLocaleString()} completed ${count === 1 ? "batch" : "batches"}. In-progress, incomplete, and cancelled batches are not affected. This action cannot be undone.`,
  },
  finished: {
    label: "Clear finished batches",
    title: "Clear finished batches?",
    description: (count) =>
      `Permanently remove ${count.toLocaleString()} complete or incomplete ${count === 1 ? "batch" : "batches"}. In-progress and cancelled batches are not affected. This action cannot be undone.`,
  },
  cancelled: {
    label: "Clear cancelled batches",
    title: "Clear cancelled batches?",
    description: (count) =>
      `Permanently remove ${count.toLocaleString()} cancelled ${count === 1 ? "batch" : "batches"} with no pending or reserved jobs. Cancelled batches that still have live work are not affected. This action cannot be undone.`,
  },
};

export function BatchesActions({
  horizonBaseUrl,
  counts,
}: {
  horizonBaseUrl: string;
  counts: BatchClearCounts;
}) {
  const [scope, setScope] = useState<BatchClearScope | null>(null);
  const [working, setWorking] = useState(false);
  const copy = scope ? clearCopy[scope] : null;
  const count = scope ? counts[scope] : 0;

  const clear = () => {
    if (!scope) {
      return;
    }

    const route = resolveHorizonRoute(clearBatches(scope), horizonBaseUrl);

    router.delete(route.url, {
      preserveScroll: true,
      onStart: () => setWorking(true),
      onSuccess: () => setScope(null),
      onFinish: () => setWorking(false),
    });
  };

  const hasActions = counts.available && clearScopes.some((actionScope) => counts[actionScope] > 0);

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger available={hasActions} label="Batch actions" working={working} />
        <DropdownMenuContent align="end" className="w-52">
          {clearScopes.map((actionScope) => (
            <DropdownMenuItem
              key={actionScope}
              variant="destructive"
              disabled={!counts.available || counts[actionScope] === 0}
              onSelect={() => setScope(actionScope)}
            >
              <Trash2Icon />
              {clearCopy[actionScope].label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={scope !== null} onOpenChange={(open) => !open && setScope(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{copy?.title}</DialogTitle>
            <DialogDescription>{copy?.description(count)}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button type="button" variant="destructive" disabled={working} onClick={clear}>
              {working ? <LoaderCircleIcon className="animate-spin" /> : <Trash2Icon />}
              {working ? "Clearing…" : copy?.label}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
