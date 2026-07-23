import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

import { QueueActionsMenu } from "@/components/queues/queue-actions-menu";

const inertia = vi.hoisted(() => ({ delete: vi.fn(), post: vi.fn() }));

vi.mock("@inertiajs/react", () => ({
  router: inertia,
}));

describe("QueueActionsMenu", () => {
  beforeEach(() => {
    inertia.delete.mockReset();
    inertia.post.mockReset();
  });

  it("opens a pause dialog and pauses indefinitely by default", async () => {
    render(
      <QueueActionsMenu
        connection="redis#west"
        queue="reports?urgent"
        paused={false}
        horizonBaseUrl="/horizon"
      />,
    );

    fireEvent.pointerDown(
      screen.getByRole("button", { name: "Queue actions for reports?urgent" }),
      {
        button: 0,
        ctrlKey: false,
      },
    );
    fireEvent.click(await screen.findByRole("menuitem", { name: "Pause" }));

    expect(screen.getByRole("dialog", { name: "Pause reports?urgent" })).toBeInTheDocument();
    expect(screen.getByRole("switch", { name: "Pause indefinitely" })).toBeChecked();

    fireEvent.click(screen.getByRole("button", { name: "Pause queue" }));

    expect(inertia.post).toHaveBeenCalledWith(
      "/horizon/queues/redis%23west/reports%3Furgent/pause",
      { duration_minutes: null },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("prefills a timed pause from the modal presets", async () => {
    render(
      <QueueActionsMenu
        connection="redis"
        queue="reports"
        paused={false}
        horizonBaseUrl="/horizon"
      />,
    );

    fireEvent.pointerDown(screen.getByRole("button", { name: "Queue actions for reports" }), {
      button: 0,
      ctrlKey: false,
    });
    fireEvent.click(await screen.findByRole("menuitem", { name: "Pause" }));
    fireEvent.click(screen.getByRole("switch", { name: "Pause indefinitely" }));
    fireEvent.click(screen.getByRole("button", { name: "1 hour" }));

    expect(screen.getByRole("spinbutton", { name: "Pause for" })).toHaveValue(60);

    fireEvent.click(screen.getByRole("button", { name: "Pause queue" }));

    expect(inertia.post).toHaveBeenCalledWith(
      "/horizon/queues/redis/reports/pause",
      { duration_minutes: 60 },
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("offers resume and replacement pause options for a paused queue", async () => {
    render(
      <QueueActionsMenu connection="redis" queue="reports" paused horizonBaseUrl="/horizon" />,
    );

    fireEvent.pointerDown(screen.getByRole("button", { name: "Queue actions for reports" }), {
      button: 0,
      ctrlKey: false,
    });
    fireEvent.click(await screen.findByRole("menuitem", { name: "Resume now" }));

    expect(inertia.delete).toHaveBeenCalledWith(
      "/horizon/queues/redis/reports/pause",
      expect.objectContaining({ preserveScroll: true }),
    );
  });

  it("uses the exact target live total when deciding whether Clear is available", async () => {
    const { unmount } = render(
      <QueueActionsMenu
        queue="reports"
        targets={[
          {
            connection: "redis",
            paused: false,
            pausedUntil: null,
            ready: 0,
            reserved: 0,
            delayed: 0,
            total: 0,
          },
        ]}
        pendingJobs={20}
        horizonBaseUrl="/horizon"
      />,
    );

    fireEvent.pointerDown(screen.getByRole("button", { name: "Queue actions for reports" }), {
      button: 0,
      ctrlKey: false,
    });

    expect(await screen.findByRole("menuitem", { name: "Clear queue" })).toHaveAttribute(
      "aria-disabled",
      "true",
    );
    unmount();

    render(
      <QueueActionsMenu
        queue="reports"
        targets={[
          {
            connection: "sqs",
            paused: false,
            pausedUntil: null,
            ready: 1,
            reserved: 0,
            delayed: 0,
            total: 1,
          },
        ]}
        pendingJobs={0}
        horizonBaseUrl="/horizon"
      />,
    );

    fireEvent.pointerDown(screen.getByRole("button", { name: "Queue actions for reports" }), {
      button: 0,
      ctrlKey: false,
    });

    expect(await screen.findByRole("menuitem", { name: "Clear queue" })).not.toHaveAttribute(
      "aria-disabled",
    );
  });

  it("keeps unrelated actions available when queue pausing is unsupported", async () => {
    render(
      <QueueActionsMenu
        connection="redis"
        queue="reports"
        paused={false}
        pendingJobs={1}
        failedJobs={1}
        horizonBaseUrl="/horizon"
        queuePausing={false}
      />,
    );

    fireEvent.pointerDown(screen.getByRole("button", { name: "Queue actions for reports" }), {
      button: 0,
      ctrlKey: false,
    });

    expect(await screen.findByRole("menuitem", { name: "Retry all failed jobs" })).toBeVisible();
    expect(screen.getByRole("menuitem", { name: "Clear queue" })).toBeVisible();
    expect(screen.queryByRole("menuitem", { name: "Pause" })).not.toBeInTheDocument();
  });
});
