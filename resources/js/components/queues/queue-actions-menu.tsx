import { router } from "@inertiajs/react";
import { PauseIcon, PlayIcon, RotateCcwIcon, Trash2Icon } from "lucide-react";
import { useState } from "react";

import { Badge } from "@/components/ui/badge";
import { ActionMenuTrigger } from "@/components/ui/action-menu-trigger";
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
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
} from "@/components/ui/dropdown-menu";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
  InputGroupText,
} from "@/components/ui/input-group";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Switch } from "@/components/ui/switch";
import { destroy as clearQueue } from "@/generated/routes/horizon-new-dawn/queues/clear";
import {
  destroy as resumeQueue,
  store as pauseQueue,
} from "@/generated/routes/horizon-new-dawn/queues/pause";
import { store as retryFailedQueueJobs } from "@/generated/routes/horizon-new-dawn/queues/retry-failed";
import { resolveHorizonRoute } from "@/lib/horizon-route";

const deadlineFormatter = new Intl.DateTimeFormat(undefined, {
  month: "short",
  day: "numeric",
  hour: "numeric",
  minute: "2-digit",
});

const durations = [
  { label: "15 min", minutes: 15 },
  { label: "1 hour", minutes: 60 },
  { label: "4 hours", minutes: 240 },
] as const;

function queueRouteParameters(target: QueueActionTarget, queue: string) {
  return {
    connection: encodeURIComponent(target.connection),
    queue: encodeURIComponent(queue),
  };
}

export function QueuePauseBadge({
  paused,
  pausedUntil,
  connection,
}: {
  paused: boolean;
  pausedUntil: number | null;
  connection?: string;
}) {
  if (!paused) {
    return null;
  }

  const status = pausedUntil
    ? `Paused until ${deadlineFormatter.format(new Date(pausedUntil * 1000))}`
    : "Paused";
  const label = connection ? `${connection}: ${status}` : status;

  return <Badge variant="delayed">{label}</Badge>;
}

export type QueueActionTarget = {
  connection: string;
  paused: boolean;
  pausedUntil: number | null;
  ready?: number;
  reserved?: number;
  delayed?: number;
  total?: number;
};

type QueueActionScope = "all" | "pending" | "failed";

export function QueueActionsMenu({
  connection,
  queue,
  paused,
  pausedUntil,
  targets,
  pendingJobs,
  failedJobs,
  horizonBaseUrl,
  queuePausing = true,
  scope = "all",
}: {
  connection?: string;
  queue: string;
  paused?: boolean;
  pausedUntil?: number | null;
  targets?: QueueActionTarget[];
  pendingJobs?: number | null;
  failedJobs?: number | null;
  horizonBaseUrl: string;
  queuePausing?: boolean;
  scope?: QueueActionScope;
}) {
  const actionTargets =
    targets ??
    (connection ? [{ connection, paused: paused ?? false, pausedUntil: pausedUntil ?? null }] : []);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [clearDialogOpen, setClearDialogOpen] = useState(false);
  const [retryFailedDialogOpen, setRetryFailedDialogOpen] = useState(false);
  const [activeTarget, setActiveTarget] = useState<QueueActionTarget | null>(
    actionTargets[0] ?? null,
  );
  const [pauseIndefinitely, setPauseIndefinitely] = useState(true);
  const [durationMinutes, setDurationMinutes] = useState("15");
  const [working, setWorking] = useState(false);
  const options = {
    preserveScroll: true,
    onStart: () => setWorking(true),
    onFinish: () => setWorking(false),
  };

  const pause = (target: QueueActionTarget, duration: number | null) => {
    const route = resolveHorizonRoute(
      pauseQueue(queueRouteParameters(target, queue)),
      horizonBaseUrl,
    );

    router.post(
      route.url,
      { duration_minutes: duration },
      {
        ...options,
        onSuccess: () => {
          router.flushAll();
          setDialogOpen(false);
        },
      },
    );
  };

  const resume = (target: QueueActionTarget) => {
    const route = resolveHorizonRoute(
      resumeQueue(queueRouteParameters(target, queue)),
      horizonBaseUrl,
    );

    router.delete(route.url, {
      ...options,
      onSuccess: () => router.flushAll(),
    });
  };

  const clear = (target: QueueActionTarget) => {
    const route = resolveHorizonRoute(
      clearQueue(queueRouteParameters(target, queue)),
      horizonBaseUrl,
    );

    router.delete(route.url, {
      ...options,
      onSuccess: () => setClearDialogOpen(false),
    });
  };

  const retryFailed = (target: QueueActionTarget) => {
    const route = resolveHorizonRoute(
      retryFailedQueueJobs(queueRouteParameters(target, queue)),
      horizonBaseUrl,
    );

    router.post(
      route.url,
      {},
      {
        ...options,
        onSuccess: () => setRetryFailedDialogOpen(false),
      },
    );
  };

  const openPauseDialog = (target: QueueActionTarget) => {
    const pausedUntil = target.pausedUntil;
    const hasTimedPause = pausedUntil !== null;

    setActiveTarget(target);
    setPauseIndefinitely(!hasTimedPause);
    setDurationMinutes(
      hasTimedPause
        ? String(Math.max(1, Math.ceil((pausedUntil * 1000 - Date.now()) / 60_000)))
        : "15",
    );
    setDialogOpen(true);
  };

  const openClearDialog = (target: QueueActionTarget) => {
    setActiveTarget(target);
    setClearDialogOpen(true);
  };

  const openRetryFailedDialog = (target: QueueActionTarget) => {
    setActiveTarget(target);
    setRetryFailedDialogOpen(true);
  };

  const submitPause = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (activeTarget) {
      pause(activeTarget, pauseIndefinitely ? null : Number.parseInt(durationMinutes, 10));
    }
  };

  const pauseIndefinitelyId = `pause-indefinitely-${activeTarget?.connection ?? "queue"}-${queue}`;
  const pauseDurationId = `pause-duration-${activeTarget?.connection ?? "queue"}-${queue}`;
  const duration = Number.parseInt(durationMinutes, 10);
  const durationInvalid = !pauseIndefinitely && (!Number.isInteger(duration) || duration < 1);
  const hasAvailableActions = actionTargets.some((target) => {
    const hasPendingActions =
      scope !== "failed" && (queuePausing || (target.total ?? pendingJobs ?? 0) > 0);
    const hasFailedActions =
      scope !== "pending" && failedJobs !== undefined && (failedJobs ?? 0) > 0;

    return hasPendingActions || hasFailedActions;
  });

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger
          available={hasAvailableActions}
          label={
            scope === "all"
              ? `Queue actions for ${queue}`
              : `${scope === "pending" ? "Pending" : "Failed"} jobs actions for ${queue}`
          }
          working={working}
        />
        <DropdownMenuContent align="end" className="w-44">
          {actionTargets.length === 1 ? (
            <QueueActionItems
              canClear={(actionTargets[0].total ?? pendingJobs ?? 0) > 0}
              canPause={queuePausing}
              canRetryFailed={failedJobs === undefined ? null : (failedJobs ?? 0) > 0}
              scope={scope}
              target={actionTargets[0]}
              onClear={openClearDialog}
              onPause={openPauseDialog}
              onRetryFailed={openRetryFailedDialog}
              onResume={resume}
            />
          ) : (
            <DropdownMenuGroup>
              {actionTargets.map((target) => (
                <DropdownMenuSub key={target.connection}>
                  <DropdownMenuSubTrigger>{target.connection}</DropdownMenuSubTrigger>
                  <DropdownMenuSubContent className="w-44">
                    <QueueActionItems
                      canClear={(target.total ?? pendingJobs ?? 0) > 0}
                      canPause={queuePausing}
                      canRetryFailed={failedJobs === undefined ? null : (failedJobs ?? 0) > 0}
                      scope={scope}
                      target={target}
                      onClear={openClearDialog}
                      onPause={openPauseDialog}
                      onRetryFailed={openRetryFailedDialog}
                      onResume={resume}
                    />
                  </DropdownMenuSubContent>
                </DropdownMenuSub>
              ))}
            </DropdownMenuGroup>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <form onSubmit={submitPause}>
            <DialogHeader>
              <DialogTitle>Pause {queue}</DialogTitle>
              <DialogDescription>
                Choose when Horizon should resume processing this queue
                {actionTargets.length > 1 && activeTarget ? ` on ${activeTarget.connection}` : ""}.
              </DialogDescription>
            </DialogHeader>

            <FieldGroup className="gap-5 py-5">
              <Field orientation="horizontal" className="items-center justify-between gap-4">
                <FieldLabel htmlFor={pauseIndefinitelyId}>Pause indefinitely</FieldLabel>
                <Switch
                  id={pauseIndefinitelyId}
                  checked={pauseIndefinitely}
                  onCheckedChange={setPauseIndefinitely}
                />
              </Field>

              {!pauseIndefinitely ? (
                <Field className="gap-2.5">
                  <div className="flex items-center justify-between gap-4">
                    <FieldLabel htmlFor={pauseDurationId}>Pause for</FieldLabel>
                    <div className="flex items-center gap-1.5" aria-label="Pause duration presets">
                      {durations.map((preset, index) => (
                        <span key={preset.minutes} className="flex items-center gap-1.5">
                          {index > 0 ? (
                            <span aria-hidden="true" className="text-muted-foreground/50">
                              |
                            </span>
                          ) : null}
                          <Button
                            type="button"
                            variant="link"
                            size="xs"
                            className="h-auto p-0 text-xs font-normal"
                            onClick={() => setDurationMinutes(String(preset.minutes))}
                          >
                            {preset.label}
                          </Button>
                        </span>
                      ))}
                    </div>
                  </div>
                  <InputGroup className="h-9 has-[>[data-align=inline-end]]:[&>input]:pr-2.5">
                    <InputGroupInput
                      className="h-full px-2.5 py-1.5"
                      id={pauseDurationId}
                      type="number"
                      inputMode="numeric"
                      min={1}
                      max={525600}
                      required
                      value={durationMinutes}
                      onChange={(event) => setDurationMinutes(event.target.value)}
                    />
                    <InputGroupAddon align="inline-end" className="py-0 pr-2.5">
                      <InputGroupText className="leading-none">minutes</InputGroupText>
                    </InputGroupAddon>
                  </InputGroup>
                </Field>
              ) : null}
            </FieldGroup>

            <DialogFooter>
              <DialogClose render={<Button type="button" variant="ghost" />}>Cancel</DialogClose>
              <Button type="submit" disabled={working || durationInvalid}>
                {working ? "Pausing…" : "Pause queue"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={retryFailedDialogOpen} onOpenChange={setRetryFailedDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Retry all failed jobs from {queue}?</DialogTitle>
            <DialogDescription>
              Schedule every eligible failed job from this queue
              {activeTarget ? ` on ${activeTarget.connection}` : ""} for retry. Previously retried
              jobs are skipped.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" disabled={working} />}>
              Cancel
            </DialogClose>
            <Button
              type="button"
              disabled={working || !activeTarget}
              onClick={() => activeTarget && retryFailed(activeTarget)}
            >
              <RotateCcwIcon data-icon="inline-start" />
              {working ? "Retrying…" : "Retry all failed jobs"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={clearDialogOpen} onOpenChange={setClearDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clear {queue}?</DialogTitle>
            <DialogDescription>
              Permanently delete all pending, delayed, and reserved jobs from this queue
              {activeTarget ? ` on ${activeTarget.connection}` : ""}. Jobs already processing may
              still finish. This cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose render={<Button type="button" variant="ghost" />}>Cancel</DialogClose>
            <Button
              type="button"
              variant="destructive"
              disabled={working || !activeTarget}
              onClick={() => activeTarget && clear(activeTarget)}
            >
              {working ? "Clearing…" : "Clear queue"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function QueueActionItems({
  canClear,
  canPause,
  canRetryFailed,
  scope,
  target,
  onClear,
  onPause,
  onRetryFailed,
  onResume,
}: {
  canClear: boolean;
  canPause: boolean;
  canRetryFailed: boolean | null;
  scope: QueueActionScope;
  target: QueueActionTarget;
  onClear: (target: QueueActionTarget) => void;
  onPause: (target: QueueActionTarget) => void;
  onRetryFailed: (target: QueueActionTarget) => void;
  onResume: (target: QueueActionTarget) => void;
}) {
  const showPendingActions = scope === "all" || scope === "pending";
  const showFailedActions = scope === "all" || scope === "failed";

  return (
    <DropdownMenuGroup>
      {showPendingActions && canPause && target.paused ? (
        <>
          <DropdownMenuItem onSelect={() => onResume(target)}>
            <PlayIcon />
            Resume now
          </DropdownMenuItem>
          <DropdownMenuSeparator />
        </>
      ) : null}
      {showPendingActions && canPause ? (
        <>
          <DropdownMenuItem onSelect={() => onPause(target)}>
            <PauseIcon />
            {target.paused ? "Update pause" : "Pause"}
          </DropdownMenuItem>
          <DropdownMenuSeparator />
        </>
      ) : null}
      {showFailedActions && canRetryFailed !== null ? (
        <>
          <DropdownMenuItem disabled={!canRetryFailed} onSelect={() => onRetryFailed(target)}>
            <RotateCcwIcon />
            Retry all failed jobs
          </DropdownMenuItem>
          {showPendingActions ? <DropdownMenuSeparator /> : null}
        </>
      ) : null}
      {showPendingActions ? (
        <DropdownMenuItem
          variant="destructive"
          disabled={!canClear}
          onSelect={() => onClear(target)}
        >
          <Trash2Icon />
          Clear queue
        </DropdownMenuItem>
      ) : null}
    </DropdownMenuGroup>
  );
}
