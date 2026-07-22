import { act, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import {
  HorizonInstanceActions,
  HorizonInstancesActions,
  SupervisorActions,
} from "@/components/dashboard/horizon-instances-actions";

const inertia = vi.hoisted(() => ({ reload: vi.fn() }));
const fetchMock = vi.hoisted(() => vi.fn());

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

  it("targets the exact instance and revalidates once after pausing", async () => {
    render(
      <HorizonInstanceActions
        horizonBaseUrl="/horizon"
        instanceName="local-host-a1b2"
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
    expect(trigger).toBeDisabled();
    expect(inertia.reload).not.toHaveBeenCalled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1_099);
    });

    expect(inertia.reload).not.toHaveBeenCalled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1);
    });

    expect(inertia.reload).toHaveBeenCalledTimes(1);
    expect(inertia.reload).toHaveBeenCalledWith(
      expect.objectContaining({
        only: ["supervisors", "horizon", "navigationCounts"],
        onFinish: expect.any(Function),
      }),
    );
    expect(trigger).toBeDisabled();

    const reloadOptions = inertia.reload.mock.calls[0]?.[0] as { onFinish?: () => void };

    act(() => reloadOptions.onFinish?.());

    expect(trigger).not.toBeDisabled();
  });

  it("cancels a pending revalidation when the action unmounts", async () => {
    const { unmount } = render(
      <HorizonInstanceActions
        horizonBaseUrl="/horizon"
        instanceName="local-host-a1b2"
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
    expect(inertia.reload).not.toHaveBeenCalled();

    unmount();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1_100);
    });

    expect(inertia.reload).not.toHaveBeenCalled();
  });

  it("encodes the exact supervisor and revalidates once after continuing", async () => {
    render(
      <SupervisorActions
        horizonBaseUrl="/horizon"
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
    expect(inertia.reload).toHaveBeenCalledTimes(1);
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
    expect(inertia.reload).not.toHaveBeenCalled();
  });
});
