import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import type {
  DashboardSupervisors,
  HorizonStatus,
  HorizonTransitionStatus,
  ProcessTransitions,
} from "@/types/dashboard";

const fallbackTransitionPollInterval = 5_000;
const minimumTransitionTimeout = 30_000;
const transitionPollAttempts = 6;
const transitionTimeoutMessage =
  "Horizon did not report the requested state. Showing the latest status.";

type PendingTransition = {
  status: HorizonTransitionStatus;
  token: number;
  expiresAt: number;
};

type PendingProcessTransitions = {
  instances: Record<string, PendingTransition>;
  supervisors: Record<string, PendingTransition>;
};

export function resolveProcessPollInterval(configuredInterval: number): number {
  return configuredInterval > 0 ? configuredInterval : fallbackTransitionPollInterval;
}

function resolveProcessTransitionTimeout(configuredInterval: number): number {
  return Math.max(
    minimumTransitionTimeout,
    resolveProcessPollInterval(configuredInterval) * transitionPollAttempts,
  );
}

function emptyPendingTransitions(): PendingProcessTransitions {
  return {
    instances: {},
    supervisors: {},
  };
}

function targetStatus(transition: HorizonTransitionStatus): HorizonStatus {
  return transition === "pausing" ? "paused" : "running";
}

function reconcileTransitions(
  transitions: PendingProcessTransitions,
  supervisors: DashboardSupervisors,
): PendingProcessTransitions {
  if (!supervisors.available) {
    return transitions;
  }

  const groups = new Map(supervisors.groups.map((group) => [group.name, group]));
  const children = new Map(
    supervisors.groups.flatMap((group) =>
      group.items.map((supervisor) => [supervisor.id, supervisor] as const),
    ),
  );
  const instances = { ...transitions.instances };
  const supervisorTransitions = { ...transitions.supervisors };
  let changed = false;

  for (const [instanceName, transition] of Object.entries(instances)) {
    const group = groups.get(instanceName);
    const expectedStatus = targetStatus(transition.status);
    const statuses =
      group === undefined ? [] : [group.status, ...group.items.map((item) => item.status)];

    if (group === undefined || statuses.every((status) => status === expectedStatus)) {
      delete instances[instanceName];
      changed = true;
    }
  }

  for (const [supervisorId, transition] of Object.entries(supervisorTransitions)) {
    const supervisor = children.get(supervisorId);

    if (supervisor === undefined || supervisor.status === targetStatus(transition.status)) {
      delete supervisorTransitions[supervisorId];
      changed = true;
    }
  }

  return changed
    ? {
        instances,
        supervisors: supervisorTransitions,
      }
    : transitions;
}

function publicTransitions(transitions: PendingProcessTransitions): ProcessTransitions {
  return {
    instances: Object.fromEntries(
      Object.entries(transitions.instances).map(([name, transition]) => [name, transition.status]),
    ),
    supervisors: Object.fromEntries(
      Object.entries(transitions.supervisors).map(([name, transition]) => [
        name,
        transition.status,
      ]),
    ),
  };
}

function nextExpiry(transitions: PendingProcessTransitions): number | null {
  const expiries = [
    ...Object.values(transitions.instances),
    ...Object.values(transitions.supervisors),
  ].map((transition) => transition.expiresAt);

  return expiries.length === 0 ? null : Math.min(...expiries);
}

function withoutExpiredTransitions(
  transitions: PendingProcessTransitions,
  expiredAt: number,
): PendingProcessTransitions {
  const instances = Object.fromEntries(
    Object.entries(transitions.instances).filter(
      ([, transition]) => transition.expiresAt > expiredAt,
    ),
  );
  const supervisors = Object.fromEntries(
    Object.entries(transitions.supervisors).filter(
      ([, transition]) => transition.expiresAt > expiredAt,
    ),
  );

  if (
    Object.keys(instances).length === Object.keys(transitions.instances).length &&
    Object.keys(supervisors).length === Object.keys(transitions.supervisors).length
  ) {
    return transitions;
  }

  return { instances, supervisors };
}

export function useProcessTransitions(
  supervisors: DashboardSupervisors | undefined,
  configuredPollInterval = 0,
) {
  const initialTransitions = useRef<PendingProcessTransitions>(emptyPendingTransitions());
  const transitionRef = useRef(initialTransitions.current);
  const nextToken = useRef(0);
  const [pendingTransitions, setPendingTransitions] = useState(initialTransitions.current);
  const commitTransitions = useCallback((transitions: PendingProcessTransitions) => {
    if (transitions === transitionRef.current) {
      return;
    }

    transitionRef.current = transitions;
    setPendingTransitions(transitions);
  }, []);

  useEffect(() => {
    if (supervisors !== undefined) {
      commitTransitions(reconcileTransitions(transitionRef.current, supervisors));
    }
  }, [commitTransitions, supervisors]);

  const onInstanceTransition = useCallback(
    (instanceName: string, status: HorizonTransitionStatus): number | null => {
      const group = supervisors?.available
        ? supervisors.groups.find((candidate) => candidate.name === instanceName)
        : undefined;
      const current = transitionRef.current;

      if (
        group === undefined ||
        current.instances[instanceName] !== undefined ||
        group.items.some((item) => current.supervisors[item.id] !== undefined)
      ) {
        return null;
      }

      const token = ++nextToken.current;

      commitTransitions({
        ...current,
        instances: {
          ...current.instances,
          [instanceName]: {
            status,
            token,
            expiresAt: Date.now() + resolveProcessTransitionTimeout(configuredPollInterval),
          },
        },
      });

      return token;
    },
    [commitTransitions, configuredPollInterval, supervisors],
  );

  const onSupervisorTransition = useCallback(
    (supervisorId: string, status: HorizonTransitionStatus): number | null => {
      const group = supervisors?.available
        ? supervisors.groups.find((candidate) =>
            candidate.items.some((item) => item.id === supervisorId),
          )
        : undefined;
      const current = transitionRef.current;

      if (
        group === undefined ||
        current.instances[group.name] !== undefined ||
        current.supervisors[supervisorId] !== undefined
      ) {
        return null;
      }

      const token = ++nextToken.current;

      commitTransitions({
        ...current,
        supervisors: {
          ...current.supervisors,
          [supervisorId]: {
            status,
            token,
            expiresAt: Date.now() + resolveProcessTransitionTimeout(configuredPollInterval),
          },
        },
      });

      return token;
    },
    [commitTransitions, configuredPollInterval, supervisors],
  );

  const onInstanceTransitionFailure = useCallback(
    (instanceName: string, token: number) => {
      const current = transitionRef.current;

      if (current.instances[instanceName]?.token !== token) {
        return;
      }

      const instances = { ...current.instances };
      delete instances[instanceName];
      commitTransitions({ ...current, instances });
    },
    [commitTransitions],
  );

  const onSupervisorTransitionFailure = useCallback(
    (supervisorId: string, token: number) => {
      const current = transitionRef.current;

      if (current.supervisors[supervisorId]?.token !== token) {
        return;
      }

      const supervisorTransitions = { ...current.supervisors };
      delete supervisorTransitions[supervisorId];
      commitTransitions({ ...current, supervisors: supervisorTransitions });
    },
    [commitTransitions],
  );

  const hasPendingTransitions =
    Object.keys(pendingTransitions.instances).length > 0 ||
    Object.keys(pendingTransitions.supervisors).length > 0;
  const expiresAt = nextExpiry(pendingTransitions);

  useEffect(() => {
    if (expiresAt === null) {
      return;
    }

    const expireTransitions = () => {
      const now = Date.now();

      if (now < expiresAt) {
        timeout = window.setTimeout(expireTransitions, expiresAt - now);

        return;
      }

      const current = transitionRef.current;
      const remaining = withoutExpiredTransitions(current, now);

      if (remaining === current) {
        return;
      }

      commitTransitions(remaining);
      toast.error(transitionTimeoutMessage);
    };
    let timeout = window.setTimeout(expireTransitions, Math.max(0, expiresAt - Date.now()));

    return () => window.clearTimeout(timeout);
  }, [commitTransitions, expiresAt]);

  return {
    hasPendingTransitions,
    onInstanceTransition,
    onInstanceTransitionFailure,
    onSupervisorTransition,
    onSupervisorTransitionFailure,
    transitions: publicTransitions(pendingTransitions),
  };
}
