import { PauseIcon, PlayIcon, PowerIcon } from "lucide-react";
import { router } from "@inertiajs/react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

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
import type { RouteDefinition } from "@/generated/wayfinder";
import {
  destroy as continueHorizon,
  store as pauseHorizon,
} from "@/generated/routes/horizon-new-dawn/instances/pause";
import { store as terminateHorizon } from "@/generated/routes/horizon-new-dawn/instances/terminate";
import {
  destroy as continueSupervisor,
  store as pauseSupervisor,
} from "@/generated/routes/horizon-new-dawn/supervisors/pause";
import { resolveHorizonRoute } from "@/lib/horizon-route";
import type { HorizonStatus } from "@/types/dashboard";

type HorizonCommand = "continue" | "pause" | "terminate";

const commandRevalidationDelay = 1_100;

function useDelayedInstancesRevalidation() {
  const mounted = useRef(true);
  const timeout = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    mounted.current = true;

    return () => {
      mounted.current = false;

      if (timeout.current !== null) {
        clearTimeout(timeout.current);
      }
    };
  }, []);

  return useCallback((onFinish: () => void) => {
    if (timeout.current !== null) {
      clearTimeout(timeout.current);
    }

    timeout.current = setTimeout(() => {
      timeout.current = null;

      router.reload({
        only: ["supervisors", "horizon", "navigationCounts"],
        onFinish: () => {
          if (mounted.current) {
            onFinish();
          }
        },
      });
    }, commandRevalidationDelay);
  }, []);
}

export function HorizonInstancesActions({
  horizonBaseUrl,
  hasLocalInstance,
}: {
  horizonBaseUrl: string;
  hasLocalInstance: boolean;
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [working, setWorking] = useState<HorizonCommand | null>(null);
  const revalidateInstances = useDelayedInstancesRevalidation();

  const run = async (
    action: HorizonCommand,
    routeDefinition: RouteDefinition<"delete" | "post">,
    fallback: string,
  ) => {
    const route = resolveHorizonRoute(routeDefinition, horizonBaseUrl);
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
      toast.error(fallback);

      return;
    }

    setWorking(action);

    try {
      const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: unknown;
      } | null;
      const message = typeof payload?.message === "string" ? payload.message : fallback;

      if (!response.ok) {
        toast.error(message);
        setWorking(null);

        return;
      }

      if (action === "terminate") {
        setDialogOpen(false);
      }

      toast.success(message);
      revalidateInstances(() => setWorking(null));
    } catch {
      toast.error(fallback);
      setWorking(null);
    }
  };

  const terminate = () => run("terminate", terminateHorizon(), "Horizon could not be terminated.");

  return (
    <>
      <DropdownMenu>
        <ActionMenuTrigger
          available={hasLocalInstance}
          label="Horizon instances actions"
          working={working !== null}
        />
        <DropdownMenuContent align="end" className="w-44">
          <DropdownMenuItem
            variant="destructive"
            disabled={!hasLocalInstance}
            onSelect={() => setDialogOpen(true)}
          >
            <PowerIcon />
            Terminate instances
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Terminate instances?</DialogTitle>
            <DialogDescription>
              Send the horizon:terminate command to every Horizon instance on this machine. Active
              jobs may finish before the processes exit. A process monitor may restart Horizon
              automatically.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <DialogClose
              render={<Button type="button" variant="ghost" disabled={working !== null} />}
            >
              Cancel
            </DialogClose>
            <Button
              type="button"
              variant="destructive"
              disabled={working !== null}
              onClick={terminate}
            >
              {working === "terminate" ? "Terminating…" : "Terminate instances"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

export function HorizonInstanceActions({
  horizonBaseUrl,
  instanceName,
  status,
}: {
  horizonBaseUrl: string;
  instanceName: string;
  status: HorizonStatus;
}) {
  const [working, setWorking] = useState<Exclude<HorizonCommand, "terminate"> | null>(null);
  const revalidateInstances = useDelayedInstancesRevalidation();

  const run = async (
    action: Exclude<HorizonCommand, "terminate">,
    routeDefinition: RouteDefinition<"delete" | "post">,
    fallback: string,
  ) => {
    const route = resolveHorizonRoute(routeDefinition, horizonBaseUrl);
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
      toast.error(fallback);

      return;
    }

    setWorking(action);

    try {
      const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: unknown;
      } | null;
      const message = typeof payload?.message === "string" ? payload.message : fallback;

      if (!response.ok) {
        toast.error(message);
        setWorking(null);

        return;
      }

      toast.success(message);
      revalidateInstances(() => setWorking(null));
    } catch {
      toast.error(fallback);
      setWorking(null);
    }
  };

  const encodedInstance = encodeURIComponent(instanceName);
  const pause = () => run("pause", pauseHorizon(encodedInstance), "Horizon could not be paused.");
  const resume = () =>
    run("continue", continueHorizon(encodedInstance), "Horizon could not be continued.");

  return (
    <DropdownMenu>
      <ActionMenuTrigger
        available={status === "running" || status === "paused"}
        label={`Horizon instance ${instanceName} actions`}
        working={working !== null}
        className="text-muted-foreground hover:text-foreground focus-visible:text-foreground aria-expanded:text-foreground"
      />
      <DropdownMenuContent align="end" className="w-44">
        {status === "paused" ? (
          <DropdownMenuItem onSelect={resume}>
            <PlayIcon />
            Continue instance
          </DropdownMenuItem>
        ) : (
          <DropdownMenuItem disabled={status !== "running"} onSelect={pause}>
            <PauseIcon />
            Pause instance
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function SupervisorActions({
  horizonBaseUrl,
  supervisor,
  status,
}: {
  horizonBaseUrl: string;
  supervisor: string;
  status: HorizonStatus;
}) {
  const [working, setWorking] = useState<"continue" | "pause" | null>(null);
  const revalidateInstances = useDelayedInstancesRevalidation();

  const run = async (
    action: "continue" | "pause",
    routeDefinition: RouteDefinition<"delete" | "post">,
    fallback: string,
  ) => {
    const route = resolveHorizonRoute(routeDefinition, horizonBaseUrl);
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
      toast.error(fallback);

      return;
    }

    setWorking(action);

    try {
      const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = (await response.json().catch(() => null)) as {
        message?: unknown;
      } | null;
      const message = typeof payload?.message === "string" ? payload.message : fallback;

      if (!response.ok) {
        toast.error(message);
        setWorking(null);

        return;
      }

      toast.success(message);
      revalidateInstances(() => setWorking(null));
    } catch {
      toast.error(fallback);
      setWorking(null);
    }
  };

  const encodedSupervisor = encodeURIComponent(supervisor);
  const pause = () =>
    run("pause", pauseSupervisor(encodedSupervisor), "Supervisor could not be paused.");
  const resume = () =>
    run("continue", continueSupervisor(encodedSupervisor), "Supervisor could not be continued.");

  return (
    <DropdownMenu>
      <ActionMenuTrigger
        available={status === "running" || status === "paused"}
        label={`Supervisor ${supervisor} actions`}
        working={working !== null}
        className="text-muted-foreground hover:text-foreground focus-visible:text-foreground aria-expanded:text-foreground"
      />
      <DropdownMenuContent align="end" className="w-48">
        {status === "paused" ? (
          <DropdownMenuItem onSelect={resume}>
            <PlayIcon />
            Continue supervisor
          </DropdownMenuItem>
        ) : (
          <DropdownMenuItem disabled={status !== "running"} onSelect={pause}>
            <PauseIcon />
            Pause supervisor
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
