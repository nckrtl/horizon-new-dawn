import { act, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import {
  HorizonInstanceActions,
  HorizonInstancesActions,
  SupervisorActions,
} from "@/components/dashboard/horizon-instances-actions";

const inertia = vi.hoisted(() => ({ reload: vi.fn() }));
const fetchMock = vi.hoisted(() => vi.fn());
const transition = vi.hoisted(() => vi.fn());
const transitionFailure = vi.hoisted(() => vi.fn());

vi.mock("@inertiajs/react", () => ({
  router: inertia,
}));

vi.mock("sonner", () => ({
  toast: {
    error: vi.fn(),
    success: vi.fn(),
  },
}));

vi.mock("@/generated/routes/horizon-new-dawn/instances/pause", () => ({
  store: (instance: string) => ({
    url: "/horizon/instances/" + instance + "/pause",
    method: "post",
  }),
  destroy: (instance: string) => ({
    url: "/horizon/instances/" + instance + "/pause",
    method: "delete",
  }),
}));

vi.mock("@/generated/routes/horizon-new-dawn/instances/terminate", () => ({
  store: () => ({
    url: "/horizon/instances/terminate",
    method: "post",
  }),
}));

vi.mock("@/generated/routes/horizon-new-dawn/supervisors/pause", () => ({
  store: (supervisor: string) => ({
    url: "/horizon/supervisors/" + supervisor + "/pause",
    method: "post",
  }),
  destroy: (supervisor: string) => ({
    url: "/horizon/supervisors/" + supervisor + "/pause",
    method: "delete",
  }),
}));

describe("Horizon instance actions", () => {
  beforeEach(() => {
    inertia.reload.mockReset();
    fetchMock.mockReset();
    transition.mockReset();
    transitionFailure.mockReset();
    transition.mockReturnValue(42);
    fetchMock.mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({ message: "Command queued." }),
    });
    vi.stubGlobal("fetch", fetchMock);

    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("targets the exact instance and reports its pausing transition without reloading", async () => {
    render(
      <HorizonInstanceActions
        horizonBaseUrl="/horizon"
        instanceName="local-host-a1b2"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        status="running"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", { name: "Horizon instance local-host-a1b2 actions" }),
      { button: 0, ctrlKey: false },
    );
    const trigger = screen.getByRole("button", {
      name: "Horizon instance local-host-a1b2 actions",
    });
    const pause = await screen.findByRole("menuitem", { name: "Pause instance" });

    vi.useFakeTimers();

    await act(async () => {
      fireEvent.click(pause);
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(fetchMock).toHaveBeenCalledWith(
      "/horizon/instances/local-host-a1b2/pause",
      expect.objectContaining({ method: "POST" }),
    );
    expect(transition).toHaveBeenCalledOnce();
    expect(transition).toHaveBeenCalledWith("pausing");
    expect(transitionFailure).not.toHaveBeenCalled();
    expect(inertia.reload).not.toHaveBeenCalled();
    expect(trigger).not.toBeDisabled();
  });

  it("does not leave a delayed revalidation behind when the action unmounts", async () => {
    const { unmount } = render(
      <HorizonInstanceActions
        horizonBaseUrl="/horizon"
        instanceName="local-host-a1b2"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        status="running"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", { name: "Horizon instance local-host-a1b2 actions" }),
      { button: 0, ctrlKey: false },
    );
    const pause = await screen.findByRole("menuitem", { name: "Pause instance" });

    vi.useFakeTimers();

    await act(async () => {
      fireEvent.click(pause);
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(transition).toHaveBeenCalledWith("pausing");
    expect(inertia.reload).not.toHaveBeenCalled();

    unmount();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1_100);
    });

    expect(inertia.reload).not.toHaveBeenCalled();
  });

  it("encodes the exact supervisor and reports its continuing transition without reloading", async () => {
    render(
      <SupervisorActions
        horizonBaseUrl="/horizon"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        supervisor="local-host-a1b2:supervisor-1"
        status="paused"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", {
        name: "Supervisor local-host-a1b2:supervisor-1 actions",
      }),
      { button: 0, ctrlKey: false },
    );
    const resume = await screen.findByRole("menuitem", { name: "Continue supervisor" });

    vi.useFakeTimers();

    await act(async () => {
      fireEvent.click(resume);
      await vi.advanceTimersByTimeAsync(1_100);
    });

    expect(fetchMock).toHaveBeenCalledWith(
      "/horizon/supervisors/local-host-a1b2%3Asupervisor-1/pause",
      expect.objectContaining({ method: "DELETE" }),
    );
    expect(transition).toHaveBeenCalledOnce();
    expect(transition).toHaveBeenCalledWith("continuing");
    expect(inertia.reload).not.toHaveBeenCalled();
  });

  it("revalidates once after terminating local instances", async () => {
    render(<HorizonInstancesActions horizonBaseUrl="/horizon" hasLocalInstance />);

    fireEvent.pointerDown(screen.getByRole("button", { name: "Horizon instances actions" }), {
      button: 0,
      ctrlKey: false,
    });
    fireEvent.click(await screen.findByRole("menuitem", { name: "Terminate instances" }));
    const terminate = screen.getByRole("button", { name: "Terminate instances" });

    vi.useFakeTimers();

    await act(async () => {
      fireEvent.click(terminate);
      await vi.advanceTimersByTimeAsync(1_100);
    });

    expect(fetchMock).toHaveBeenCalledWith(
      "/horizon/instances/terminate",
      expect.objectContaining({ method: "POST" }),
    );
    expect(inertia.reload).toHaveBeenCalledTimes(1);
  });

  it("does not revalidate after a failed command", async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      json: vi.fn().mockResolvedValue({ message: "Command failed." }),
    });
    render(
      <HorizonInstanceActions
        horizonBaseUrl="/horizon"
        instanceName="local-host-a1b2"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        status="running"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", { name: "Horizon instance local-host-a1b2 actions" }),
      { button: 0, ctrlKey: false },
    );
    const pause = await screen.findByRole("menuitem", { name: "Pause instance" });

    vi.useFakeTimers();

    await act(async () => {
      fireEvent.click(pause);
      await vi.advanceTimersByTimeAsync(1_100);
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(transition).toHaveBeenCalledWith("pausing");
    expect(transitionFailure).toHaveBeenCalledWith(42);
    expect(inertia.reload).not.toHaveBeenCalled();
  });

  it("acquires the transition before waiting for the command response", async () => {
    let resolveResponse: ((response: unknown) => void) | undefined;
    fetchMock.mockReturnValue(
      new Promise((resolve) => {
        resolveResponse = resolve;
      }),
    );

    render(
      <SupervisorActions
        horizonBaseUrl="/horizon"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        supervisor="local-host-a1b2:supervisor-1"
        status="paused"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", {
        name: "Supervisor local-host-a1b2:supervisor-1 actions",
      }),
      { button: 0, ctrlKey: false },
    );
    fireEvent.click(await screen.findByRole("menuitem", { name: "Continue supervisor" }));

    expect(transition).toHaveBeenCalledWith("continuing");
    expect(fetchMock).toHaveBeenCalledOnce();

    await act(async () => {
      resolveResponse?.({
        ok: true,
        json: vi.fn().mockResolvedValue({ message: "Command queued." }),
      });
    });

    expect(transitionFailure).not.toHaveBeenCalled();
  });

  it("rolls back and unlocks when the command request times out", async () => {
    fetchMock.mockImplementation(
      (_url: string, init?: RequestInit) =>
        new Promise((_resolve, reject) => {
          init?.signal?.addEventListener(
            "abort",
            () => reject(new DOMException("The operation was aborted.", "AbortError")),
            { once: true },
          );
        }),
    );

    render(
      <SupervisorActions
        horizonBaseUrl="/horizon"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        supervisor="local-host-a1b2:supervisor-1"
        status="paused"
      />,
    );

    const trigger = screen.getByRole("button", {
      name: "Supervisor local-host-a1b2:supervisor-1 actions",
    });
    fireEvent.pointerDown(trigger, { button: 0, ctrlKey: false });
    const resume = await screen.findByRole("menuitem", { name: "Continue supervisor" });

    vi.useFakeTimers();
    fireEvent.click(resume);

    expect(trigger).toBeDisabled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(30_000);
    });

    expect(transitionFailure).toHaveBeenCalledWith(42);
    expect(trigger).not.toBeDisabled();
  });

  it("does not dispatch a command when transition acquisition is rejected", async () => {
    transition.mockReturnValue(null);

    render(
      <HorizonInstanceActions
        horizonBaseUrl="/horizon"
        instanceName="local-host-a1b2"
        onTransition={transition}
        onTransitionFailure={transitionFailure}
        status="running"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", { name: "Horizon instance local-host-a1b2 actions" }),
      { button: 0, ctrlKey: false },
    );
    fireEvent.click(await screen.findByRole("menuitem", { name: "Pause instance" }));

    expect(transition).toHaveBeenCalledWith("pausing");
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
